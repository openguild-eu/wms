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
use Modules\Orders\Events\OrderCreated;

class DispatchOrderCreatedWebhook implements ShouldQueue, ShouldQueueAfterCommit
{
    use InteractsWithQueue;

    public function __construct(private readonly WebhookDispatcher $webhookDispatcher) {}

    public function handle(OrderCreated $event): void
    {
        $order = $event->order->fresh(['client', 'products']);

        if (! $order) {
            return;
        }

        $warehouse = $order->warehouse;

        if (! $warehouse instanceof Warehouse) {
            return;
        }

        $this->webhookDispatcher->dispatch(
            $warehouse,
            WebhookEvent::OrderCreated,
            (new OrderResource($order))->resolve(),
        );
    }
}
