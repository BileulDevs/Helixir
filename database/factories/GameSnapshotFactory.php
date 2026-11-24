<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\GameSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameSnapshot>
 */
class GameSnapshotFactory extends Factory
{
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'collected_at' => now()->startOfMinute(),
            'streams_count' => fake()->numberBetween(10, 5000),
            'viewers_count' => fake()->numberBetween(100, 300000),
            'is_estimate' => false,
        ];
    }
}
