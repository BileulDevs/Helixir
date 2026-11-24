<?php

namespace Database\Factories;

use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => (string) fake()->unique()->numberBetween(1000, 999999),
            'name' => fake()->unique()->words(2, true),
            'box_art_url' => null,
        ];
    }
}
