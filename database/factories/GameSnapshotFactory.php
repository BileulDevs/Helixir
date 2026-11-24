<?php

namespace Database\Factories;

use App\Models\CollectionRun;
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

    /** Par défaut, le snapshot appartient à un cycle terminé, donc visible dans l'API. */
    public function configure(): static
    {
        return $this->afterCreating(fn (GameSnapshot $snapshot) => CollectionRun::firstOrCreate(
            ['collected_at' => $snapshot->collected_at],
            ['games_count' => 0, 'completed_at' => now()],
        ));
    }
}
