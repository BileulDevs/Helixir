<?php

use App\Models\GameSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('is stale when nothing has been collected', function () {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertExactJson(['data' => ['status' => 'stale', 'last_snapshot_at' => null]]);
});

it('is ok when the last snapshot is recent', function () {
    $this->freezeTime();
    GameSnapshot::factory()->create(['collected_at' => now()->subMinutes(3)]);

    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonPath('data.status', 'ok')
        ->assertJsonPath('data.last_snapshot_at', now()->subMinutes(3)->toIso8601ZuluString());
});

it('is stale when the last snapshot is too old', function () {
    GameSnapshot::factory()->create(['collected_at' => now()->subMinutes(30)]);

    $this->getJson('/api/v1/health')->assertJsonPath('data.status', 'stale');
});
