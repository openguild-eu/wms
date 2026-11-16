<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\WebhookEvent;
use App\Http\Resources\OrderResource;
use App\Models\Warehouse;
use App\Services\WebhookDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Orders\Events\OrderStatusChanged;

class DispatchOrderStatusChangedWebhook implements ShouldQueue, ShouldQueueAfterCommit
{
    use InteractsWithQueue;

    public function __construct(private readonly WebhookDispatcher $webhookDispatcher) {}

    public function handle(OrderStatusChanged $event): void
    {
        $order = $event->order->fresh(['client', 'products']);

        if (! $order) {
            return;
        }

        $warehouse = $order->warehouse;

        if (! $warehouse instanceof Warehouse) {
            return;
        }

        $payload = (new OrderResource($order))->resolve();

        $this->webhookDispatcher->dispatch($warehouse, WebhookEvent::OrderStatusChanged, $payload);

        $previousStatus = $event->previousStatus;
        $newStatus = $event->newStatus;

        if ($newStatus?->cancelled && ! ($previousStatus && $previousStatus->cancelled)) {
            $this->webhookDispatcher->dispatch($warehouse, WebhookEvent::OrderCancelled, $payload);
        }

        if ($newStatus?->delivered && ! ($previousStatus && $previousStatus->delivered)) {
            $this->webhookDispatcher->dispatch($warehouse, WebhookEvent::OrderDelivered, $payload);
        }
    }
}
