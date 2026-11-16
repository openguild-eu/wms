<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum WebhookEvent: string implements HasLabel
{
    case OrderCreated = 'order.created';
    case OrderStatusChanged = 'order.status_changed';
    case OrderCancelled = 'order.cancelled';
    case OrderDelivered = 'order.delivered';
    case PurchaseOrderStatusChanged = 'purchase_order.status_changed';
    case PurchaseOrderProcessed = 'purchase_order.processed';
    case BackorderCreated = 'backorder.created';
    case BackorderFulfilled = 'backorder.fulfilled';
    case BackorderCancelled = 'backorder.cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::OrderCreated => __('Order created'),
            self::OrderStatusChanged => __('Order status changed'),
            self::OrderCancelled => __('Order cancelled'),
            self::OrderDelivered => __('Order delivered'),
            self::PurchaseOrderStatusChanged => __('Purchase order status changed'),
            self::PurchaseOrderProcessed => __('Purchase order processed'),
            self::BackorderCreated => __('Backorder created'),
            self::BackorderFulfilled => __('Backorder fulfilled'),
            self::BackorderCancelled => __('Backorder cancelled'),
        };
    }
}
