<?php

declare(strict_types=1);

namespace Modules\Backorders\Services;

use App\Services\OrderStatusTransitionService;
use Illuminate\Support\Facades\DB;
use Modules\Backorders\Enums\BackorderStatus;
use Modules\Backorders\Models\Backorder;
use Modules\Orders\Models\OrderStatus;
use Modules\Products\Models\StockProduct;

class BackorderFulfillmentService
{
    public function __construct(
        private readonly OrderStatusTransitionService $orderStatusTransitionService,
    ) {}

    /**
     * @param  array<int, int|string>  $productIds
     */
    public function fulfillForProducts(array $productIds): void
    {
        foreach (array_unique(array_filter($productIds)) as $productId) {
            $this->fulfillForProduct((int) $productId);
        }
    }

    public function fulfillForProduct(int $productId): void
    {
        DB::transaction(function () use ($productId): void {
            $stockProduct = StockProduct::query()
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            if (! $stockProduct) {
                return;
            }

            $backorders = Backorder::query()
                ->where('product_id', $productId)
                ->whereIn('status', [BackorderStatus::Open, BackorderStatus::PartiallyFulfilled])
                ->orderBy('created_at')
                ->lockForUpdate()
                ->get();

            foreach ($backorders as $backorder) {
                if ($stockProduct->free_on_stock_quantity <= 0) {
                    break;
                }

                $remaining = $backorder->quantity_ordered - $backorder->quantity_fulfilled;

                if ($remaining <= 0) {
                    continue;
                }

                $allocated = min($remaining, $stockProduct->free_on_stock_quantity);
                $backorder->quantity_fulfilled += $allocated;
                $backorder->status = $backorder->quantity_fulfilled >= $backorder->quantity_ordered
                    ? BackorderStatus::Fulfilled
                    : BackorderStatus::PartiallyFulfilled;

                if ($backorder->status === BackorderStatus::Fulfilled) {
                    $backorder->resolved_at = now();
                }

                $backorder->save();

                if ($backorder->status === BackorderStatus::Fulfilled) {
                    $this->releaseOrderIfFullyFulfilled($backorder, $productId);
                    $stockProduct->refresh();
                }
            }
        });
    }

    private function releaseOrderIfFullyFulfilled(Backorder $fulfilledBackorder, int $productId): void
    {
        $order = $fulfilledBackorder->order;

        if (! $order) {
            return;
        }

        $stillWaiting = Backorder::query()
            ->where('order_id', $order->id)
            ->where('status', '!=', BackorderStatus::Fulfilled)
            ->exists();

        if ($stillWaiting) {
            return;
        }

        $intendedStatus = $fulfilledBackorder->intendedOrderStatus;

        if (! $intendedStatus instanceof OrderStatus) {
            return;
        }

        $order->update(['order_statuses_id' => $intendedStatus->id]);

        // The OrderStatusChanged listener is ShouldQueueAfterCommit and is
        // not guaranteed to have run by the time the next backorder in this
        // product's FIFO loop is evaluated - force the recompute now so
        // free_on_stock_quantity reflects this release immediately.
        $this->orderStatusTransitionService->refreshReservedStockForProductIds([$productId]);
    }
}
