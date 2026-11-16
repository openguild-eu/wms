<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\WebhookEvent;
use App\Models\Subdomain;
use App\Models\Warehouse;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookEndpoint>
 */
class WebhookEndpointFactory extends Factory
{
    public function definition(): array
    {
        $subdomain = Subdomain::query()->firstOrCreate(
            ['subdomain' => $this->faker->unique()->slug(2)],
            ['name' => $this->faker->company()]
        );

        $warehouse = Warehouse::query()->firstOrCreate(
            [
                'subdomain_id' => $subdomain->id,
                'name' => $this->faker->unique()->company(),
            ]
        );

        return [
            'warehouse_id' => $warehouse->id,
            'name' => $this->faker->words(2, true),
            'url' => $this->faker->url(),
            'secret' => 'whsec_'.Str::random(40),
            'subscribed_events' => [WebhookEvent::OrderCreated->value],
            'is_active' => true,
        ];
    }
}
