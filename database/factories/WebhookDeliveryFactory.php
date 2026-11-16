<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookEvent;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookDelivery>
 */
class WebhookDeliveryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'webhook_endpoint_id' => WebhookEndpoint::factory(),
            'event' => WebhookEvent::OrderCreated->value,
            'payload' => [
                'event' => WebhookEvent::OrderCreated->value,
                'timestamp' => now()->toIso8601String(),
                'data' => [],
            ],
            'status' => WebhookDeliveryStatus::Pending,
            'response_code' => null,
            'response_body' => null,
            'attempts' => 0,
            'next_retry_at' => null,
        ];
    }
}
