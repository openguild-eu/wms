<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookEvent;
use App\Jobs\DispatchWebhookDelivery;
use App\Models\Warehouse;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;

class WebhookDispatcher
{
    /**
     * Create a delivery for every active webhook endpoint on the given
     * warehouse that is subscribed to the given event, and queue each one
     * for delivery.
     *
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(Warehouse $warehouse, WebhookEvent $event, array $payload): void
    {
        $endpoints = WebhookEndpoint::query()
            ->where('warehouse_id', $warehouse->getKey())
            ->where('is_active', true)
            ->whereJsonContains('subscribed_events', $event->value)
            ->get();

        $envelope = [
            'event' => $event->value,
            'timestamp' => now()->toIso8601String(),
            'data' => $payload,
        ];

        foreach ($endpoints as $endpoint) {
            $delivery = WebhookDelivery::query()->create([
                'webhook_endpoint_id' => $endpoint->getKey(),
                'event' => $event->value,
                'payload' => $envelope,
                'status' => WebhookDeliveryStatus::Pending,
                'attempts' => 0,
            ]);

            DispatchWebhookDelivery::dispatch($delivery);
        }
    }
}
