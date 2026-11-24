<?php

use App\Models\Game;
use App\Models\GameSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function snapshotFor(Game $game, int $streams, int $viewers, string $at = '2026-11-24 14:05:00'): GameSnapshot
{
    return GameSnapshot::factory()->for($game)->create([
        'collected_at' => $at,
        'streams_count' => $streams,
        'viewers_count' => $viewers,
    ]);
}

describe('GET /api/v1/games', function () {
    it('returns an empty list when nothing has been collected', function () {
        $this->getJson('/api/v1/games')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('meta.collected_at', null);
    });

    it('lists games from the latest cycle sorted by viewers', function () {
        $small = Game::factory()->create(['name' => 'Small']);
        $big = Game::factory()->create(['name' => 'Big']);
        snapshotFor($small, streams: 10, viewers: 100);
        snapshotFor($big, streams: 50, viewers: 5000);
        snapshotFor($big, streams: 1, viewers: 1, at: '2026-11-24 14:00:00'); // ancien cycle

        $this->getJson('/api/v1/games')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Big')
            ->assertJsonPath('data.0.viewers', 5000)
            ->assertJsonPath('data.0.average_viewers', 100)
            ->assertJsonPath('data.1.name', 'Small')
            ->assertJsonPath('meta.collected_at', '2026-11-24T14:05:00Z')
            ->assertJsonPath('meta.total', 2);
    });

    it('sorts by streams when asked', function () {
        $fewStreams = Game::factory()->create(['name' => 'Few streams']);
        $manyStreams = Game::factory()->create(['name' => 'Many streams']);
        snapshotFor($fewStreams, streams: 5, viewers: 9000);
        snapshotFor($manyStreams, streams: 500, viewers: 1000);

        $this->getJson('/api/v1/games?sort=streams')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Many streams');
    });

    it('paginates with limit and page', function () {
        Game::factory()->count(3)->create()->each(
            fn (Game $game, int $i) => snapshotFor($game, streams: 10, viewers: 100 * ($i + 1)),
        );

        $this->getJson('/api/v1/games?limit=2&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.collected_at', '2026-11-24T14:05:00Z');
    });

    it('rejects invalid parameters with the API error format', function (string $query) {
        $this->getJson("/api/v1/games?{$query}")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed');
    })->with(['limit=0', 'limit=101', 'limit=abc', 'page=0', 'sort=name']);
});

describe('GET /api/v1/games/{id}', function () {
    it('returns the latest stats of a game', function () {
        $game = Game::factory()->create(['id' => '32982', 'name' => 'Grand Theft Auto V']);
        snapshotFor($game, streams: 1, viewers: 1, at: '2026-11-24 14:00:00');
        snapshotFor($game, streams: 3, viewers: 10);

        $this->getJson('/api/v1/games/32982')
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => '32982',
                    'name' => 'Grand Theft Auto V',
                    'box_art_url' => null,
                    'streams' => 3,
                    'viewers' => 10,
                    'average_viewers' => 3,
                    'is_estimate' => false,
                ],
                'meta' => ['language' => null, 'collected_at' => '2026-11-24T14:05:00Z'],
            ]);
    });

    it('returns 404 for an unknown game', function () {
        $this->getJson('/api/v1/games/999')
            ->assertNotFound()
            ->assertExactJson(['error' => ['code' => 'not_found', 'message' => 'Resource not found.']]);
    });

    it('returns 404 when a game has no snapshot yet', function () {
        Game::factory()->create(['id' => '42']);

        $this->getJson('/api/v1/games/42')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'not_found');
    });
});
