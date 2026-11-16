<?php

declare(strict_types=1);

namespace Modules\Orders\Observers;

use App\Models\Warehouse;
use Modules\Orders\Events\OrderCreated;
use Modules\Orders\Events\OrderStatusChanged;
use Modules\Orders\Exceptions\OrderStatusLockedException;
use Modules\Orders\Models\Order;
use Modules\Orders\Models\OrderStatus;

class OrderObserver
{
    /**
     * Handle the Order "creating" event.
     */
    public function creating(Order $order): void
    {
        if ($order->generated_year_order_id !== null && $order->generated_custom_order_id !== null) {
            return;
        }

        if (! $order->warehouse_id) {
            return;
        }

        $warehouse = $order->relationLoaded('warehouse')
            ? $order->warehouse
            : Warehouse::query()->find($order->warehouse_id);

        if (! $warehouse instanceof Warehouse) {
            return;
        }

        $prefix = strtoupper(substr((string) $warehouse->name, 0, 4));
        $year = now()->format('y');

        // Scoped by prefix+year, not warehouse_id: generated_custom_order_id is
        // globally unique, and different warehouses can share the same 4-character
        // prefix (e.g. "phae1" and "phae2" both prefix to "PHAE").
        $maxGeneratedYearOrderId = Order::query()
            ->withoutGlobalScopes()
            ->where('generated_custom_order_id', 'like', "ORDER{$prefix}{$year}%")
            ->max('generated_year_order_id');

        $nextGeneratedYearOrderId = ($maxGeneratedYearOrderId ?? 0) + 1;

        $order->generated_year_order_id ??= $nextGeneratedYearOrderId;
        $order->generated_custom_order_id ??= "ORDER{$prefix}{$year}{$order->generated_year_order_id}";
    }

    /**
     * Handle the Order "created" event.
     */
    public function created(Order $order): void
    {
        $causerId = auth()->id();

        OrderCreated::dispatch($order->withoutRelations(), is_int($causerId) ? $causerId : null);
    }

    /**
     * Handle the Order "updating" event.
     */
    public function updating(Order $order): void
    {
        if (! $order->isDirty('order_statuses_id')) {
            return;
        }

        $originalStatusId = $order->getOriginal('order_statuses_id');

        if (! $originalStatusId) {
            return;
        }

        $originalStatus = OrderStatus::query()->find($originalStatusId);

        if ($originalStatus instanceof OrderStatus && ! $originalStatus->canChangeStatus()) {
            throw new OrderStatusLockedException(
                "Order #{$order->id}'s status cannot be changed: its current status ({$originalStatus->name}) is a final state (delivered, cancelled, or completed)."
            );
        }
    }

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        if (! $order->wasChanged('order_statuses_id')) {
            return;
        }

        $previousStatusId = $order->getPrevious()['order_statuses_id'] ?? null;
        $previousStatus = $previousStatusId ? OrderStatus::query()->find($previousStatusId) : null;
        $newStatus = $order->orderStatus()->first();

        if (! $previousStatus instanceof OrderStatus) {
            $previousStatus = null;
        }

        if (! $newStatus instanceof OrderStatus) {
            $newStatus = null;
        }

        $causerId = auth()->id();

        OrderStatusChanged::dispatch($order->withoutRelations(), $previousStatus, $newStatus, is_int($causerId) ? $causerId : null);
    }
}
