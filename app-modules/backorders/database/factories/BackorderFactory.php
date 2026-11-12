<?php

declare(strict_types=1);

namespace Modules\Backorders\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Backorders\Enums\BackorderStatus;
use Modules\Backorders\Models\Backorder;

/**
 * @extends Factory<Backorder>
 */
class BackorderFactory extends Factory
{
    protected $model = Backorder::class;

    public function definition(): array
    {
        return [
            'quantity_ordered' => $this->faker->numberBetween(1, 20),
            'quantity_fulfilled' => 0,
            'status' => BackorderStatus::Open,
        ];
    }
}
