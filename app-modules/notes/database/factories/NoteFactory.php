<?php

declare(strict_types=1);

namespace Modules\Notes\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Notes\Models\Note;

/**
 * @extends Factory<Note>
 */
class NoteFactory extends Factory
{
    protected $model = Note::class;

    public function definition(): array
    {
        return [
            'body' => $this->faker->sentence(),
        ];
    }
}
