<?php

use App\Models\CollectionRun;
use App\Models\Game;
use App\Models\GameSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('never serves a cycle whose jobs are still running', function () {
    $game = Game::factory()->create(['id' => '42']);
    GameSnapshot::factory()->for($game)->create(['collected_at' => '2026-11-24 14:05:00', 'viewers_count' => 100]);

    // Cycle suivant en cours : son snapshot est écrit, mais le cycle n'est pas terminé.
    $running = CollectionRun::create(['collected_at' => '2026-11-24 14:10:00', 'games_count' => 2]);
    GameSnapshot::query()->insert([
        'game_id' => '42', 'collected_at' => '2026-11-24 14:10:00',
        'streams_count' => 1, 'viewers_count' => 999, 'is_estimate' => false,
    ]);

    $this->getJson('/api/v1/games')
        ->assertJsonPath('data.0.viewers', 100)
        ->assertJsonPath('meta.collected_at', '2026-11-24T14:05:00Z');
    $this->getJson('/api/v1/games/42')->assertJsonPath('data.viewers', 100);
    $this->getJson('/api/v1/health')->assertJsonPath('data.last_snapshot_at', '2026-11-24T14:05:00Z');

    $running->markCompleted();

    $this->getJson('/api/v1/games/42')->assertJsonPath('data.viewers', 999);
});
