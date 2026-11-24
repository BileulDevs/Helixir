<?php

use App\Models\Game;
use App\Models\GameSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('serves /games from cache within the same cycle', function () {
    $snapshot = GameSnapshot::factory()->create(['collected_at' => '2026-11-24 14:05:00', 'viewers_count' => 100]);

    $this->getJson('/api/v1/games')->assertJsonPath('data.0.viewers', 100);

    $snapshot->update(['viewers_count' => 999]);

    $this->getJson('/api/v1/games')->assertJsonPath('data.0.viewers', 100);
});

it('ignores the cache as soon as a new snapshot cycle exists', function () {
    $game = Game::factory()->create();
    GameSnapshot::factory()->for($game)->create(['collected_at' => '2026-11-24 14:05:00', 'viewers_count' => 100]);
    $this->getJson('/api/v1/games')->assertJsonPath('data.0.viewers', 100);

    GameSnapshot::factory()->for($game)->create(['collected_at' => '2026-11-24 14:10:00', 'viewers_count' => 250]);

    $this->getJson('/api/v1/games')
        ->assertJsonPath('data.0.viewers', 250)
        ->assertJsonPath('meta.collected_at', '2026-11-24T14:10:00Z');
});

it('caches each combination of parameters separately', function () {
    GameSnapshot::factory()->count(3)->create(['collected_at' => '2026-11-24 14:05:00']);

    $this->getJson('/api/v1/games?limit=1')->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/games?limit=2')->assertJsonCount(2, 'data');
});
