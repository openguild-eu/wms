<?php

declare(strict_types=1);

namespace Modules\Backorders\Services;

use Modules\Backorders\Enums\BackorderStatus;
use Modules\Backorders\Models\Backorder;
use Modules\Orders\Models\Order;

class BackorderCreationService
{
    /**
     * @param  array<int, array{product_id: int, requested: int, available: int}>  $shortfalls
     */
    public function createForOrder(Order $order, array $shortfalls, int $intendedOrderStatusId): void
    {
        $order->loadMissing('products');

        foreach ($shortfalls as $shortfall) {
            $orderProduct = $order->products->firstWhere('product_id', $shortfall['product_id']);

            if (! $orderProduct) {
                continue;
            }

            Backorder::query()->create([
                'warehouse_id' => $order->warehouse_id,
                'order_id' => $order->id,
                'order_product_id' => $orderProduct->id,
                'product_id' => $shortfall['product_id'],
                'intended_order_statuses_id' => $intendedOrderStatusId,
                'quantity_ordered' => $shortfall['requested'],
                'quantity_fulfilled' => 0,
                'status' => BackorderStatus::Open,
            ]);
        }
    }
}
