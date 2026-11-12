<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Backorders\Services\BackorderFulfillmentService;
use Modules\Orders\Exceptions\PurchaseOrderStatusLockedException;
use Modules\Orders\Models\PurchaseOrder;
use Modules\Orders\Models\PurchaseOrderStatus;
use Modules\Products\Models\StockProduct;

class PurchaseOrderProcessingService
{
    public function __construct(
        private readonly OrderStatusTransitionService $orderStatusTransitionService,
        private readonly BackorderFulfillmentService $backorderFulfillmentService,
    ) {}

    /**
     * Called after every successful barcode scan. Purely a status
     * transition once every line item is scanned - stock is no longer
     * mutated here, only by markProcessed().
     */
    public function evaluateScanCompletion(PurchaseOrder $purchaseOrder): void
    {
        if (! $purchaseOrder->canTransitionTo(PurchaseOrderStatus::Scanned)) {
            return;
        }

        if (! $purchaseOrder->isFullyScanned()) {
            return;
        }

        $purchaseOrder->status = PurchaseOrderStatus::Scanned;
        $purchaseOrder->save();
    }

    public function markPurchased(PurchaseOrder $purchaseOrder, Carbon $expectedDeliveryDate): void
    {
        if (! $purchaseOrder->canTransitionTo(PurchaseOrderStatus::Purchased)) {
            throw new PurchaseOrderStatusLockedException(
                "Purchase order #{$purchaseOrder->id} cannot be marked purchased from status {$purchaseOrder->status->value}."
            );
        }

        $purchaseOrder->expected_delivery_date = $expectedDeliveryDate;
        $purchaseOrder->status = PurchaseOrderStatus::Purchased;
        $purchaseOrder->save();

        // The PO's products just became deferral-eligible - refresh reserved
        // stock for other client orders that may now be able to defer to it.
        $this->refreshProductStock($purchaseOrder);
    }

    /**
     * Pure status confirmation - does not touch stock or received_date
     * (received_date is a plain, independently-editable field).
     */
    public function markReceived(PurchaseOrder $purchaseOrder): void
    {
        if (! $purchaseOrder->canTransitionTo(PurchaseOrderStatus::Received)) {
            throw new PurchaseOrderStatusLockedException(
                "Purchase order #{$purchaseOrder->id} cannot be marked received from status {$purchaseOrder->status->value}."
            );
        }

        $purchaseOrder->status = PurchaseOrderStatus::Received;
        $purchaseOrder->save();
    }

    /**
     * The stock-mutating step: increases StockProduct::on_stock_quantity for
     * every scanned line item, then flips status to Processed.
     */
    public function markProcessed(PurchaseOrder $purchaseOrder, ?int $causerId = null): void
    {
        if (! $purchaseOrder->canTransitionTo(PurchaseOrderStatus::Processed)) {
            throw new PurchaseOrderStatusLockedException(
                "Purchase order #{$purchaseOrder->id} cannot be marked processed from status {$purchaseOrder->status->value}."
            );
        }

        $productCounts = DB::table('purchase_orders_products')
            ->where('purchase_order_id', $purchaseOrder->id)
            ->where('scanned', true)
            ->whereNotNull('product_id')
            ->selectRaw('product_id, count(*) as quantity')
            ->groupBy('product_id')
            ->pluck('quantity', 'product_id');

        DB::transaction(function () use ($purchaseOrder, $productCounts, $causerId): void {
            foreach ($productCounts as $productId => $quantity) {
                $stockProduct = StockProduct::query()
                    ->where('product_id', $productId)
                    ->lockForUpdate()
                    ->first();

                if (! $stockProduct) {
                    continue;
                }

                $quantityBefore = $stockProduct->on_stock_quantity;

                $stockProduct->on_stock_quantity += (int) $quantity;
                $this->orderStatusTransitionService->recalculateFreeStock($stockProduct);

                activity()->withoutLogging(fn () => $stockProduct->save());

                StockMutationLogger::log(
                    $stockProduct,
                    $quantityBefore,
                    $causerId,
                    "Stock increased via purchase order {$purchaseOrder->generated_custom_purchase_order_id}",
                    [
                        'purchase_order_id' => $purchaseOrder->getKey(),
                        'purchase_order_reference' => $purchaseOrder->generated_custom_purchase_order_id,
                    ],
                );
            }

            $purchaseOrder->status = PurchaseOrderStatus::Processed;
            $purchaseOrder->save();
        });

        $this->orderStatusTransitionService->refreshReservedStockForProductIds(
            $productCounts->keys()->all()
        );

        $this->backorderFulfillmentService->fulfillForProducts(
            $productCounts->keys()->all()
        );
    }

    public function cancel(PurchaseOrder $purchaseOrder): void
    {
        if (! $purchaseOrder->canTransitionTo(PurchaseOrderStatus::Cancelled)) {
            throw new PurchaseOrderStatusLockedException(
                "Purchase order #{$purchaseOrder->id} can no longer be cancelled from status {$purchaseOrder->status->value}."
            );
        }

        $purchaseOrder->status = PurchaseOrderStatus::Cancelled;
        $purchaseOrder->save();

        // No stock reversal needed - nothing is mutated before Processed.
        // But a cancelled Purchased PO stops counting as an incoming batch,
        // so other client orders' reserved_quantity must be recomputed.
        $this->refreshProductStock($purchaseOrder);
    }

    private function refreshProductStock(PurchaseOrder $purchaseOrder): void
    {
        $productIds = $purchaseOrder->products()
            ->whereNotNull('product_id')
            ->pluck('product_id')
            ->unique()
            ->all();

        if ($productIds !== []) {
            $this->orderStatusTransitionService->refreshReservedStockForProductIds($productIds);
        }
    }
}
