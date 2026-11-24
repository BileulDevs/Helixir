<?php

use App\Models\Game;
use App\Models\GameLanguageStat;
use App\Models\GameSnapshot;
use App\Models\Streamer;
use App\Models\StreamSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => config(['helixir.collector.tracked_languages' => ['fr', 'en']]));

/** Snapshot avec répartition par langue : ['fr' => [streams, viewers], ...]. */
function snapshotWithLanguages(Game $game, array $languages, string $at = '2026-11-24 14:05:00'): GameSnapshot
{
    $snapshot = GameSnapshot::factory()->for($game)->create([
        'collected_at' => $at,
        'streams_count' => array_sum(array_column($languages, 0)),
        'viewers_count' => array_sum(array_column($languages, 1)),
    ]);

    foreach ($languages as $language => [$streams, $viewers]) {
        GameLanguageStat::create([
            'game_snapshot_id' => $snapshot->id,
            'language' => $language,
            'streams_count' => $streams,
            'viewers_count' => $viewers,
        ]);
    }

    return $snapshot;
}

function streamIn(GameSnapshot $snapshot, string $login, int $viewers, string $language): void
{
    StreamSnapshot::create([
        'game_snapshot_id' => $snapshot->id,
        'streamer_id' => Streamer::factory()->create(['login' => $login, 'display_name' => ucfirst($login)])->id,
        'viewers' => $viewers,
        'title' => "Live {$login}",
        'language' => $language,
        'started_at' => '2026-11-24 12:00:00',
    ]);
}

describe('language filter', function () {
    it('ranks games by their stats in the requested language', function () {
        $bigInEnglish = Game::factory()->create(['name' => 'Big in English']);
        $bigInFrench = Game::factory()->create(['name' => 'Big in French']);
        snapshotWithLanguages($bigInEnglish, ['en' => [100, 90000], 'fr' => [2, 100]]);
        snapshotWithLanguages($bigInFrench, ['en' => [10, 1000], 'fr' => [40, 8000]]);

        $this->getJson('/api/v1/games?language=fr')
            ->assertOk()
            ->assertJsonPath('meta.language', 'fr')
            ->assertJsonPath('data.0.name', 'Big in French')
            ->assertJsonPath('data.0.streams', 40)
            ->assertJsonPath('data.0.viewers', 8000)
            ->assertJsonPath('data.0.average_viewers', 200)
            ->assertJsonPath('data.1.viewers', 100);
    });

    it('omits games nobody streams in that language', function () {
        snapshotWithLanguages(Game::factory()->create(), ['en' => [5, 50]]);

        $this->getJson('/api/v1/games?language=fr')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });

    it('returns a game stats for one language, zero when absent', function () {
        $game = Game::factory()->create(['id' => '7']);
        snapshotWithLanguages($game, ['en' => [3, 300], 'fr' => [1, 50]]);

        $this->getJson('/api/v1/games/7?language=fr')
            ->assertOk()
            ->assertJsonPath('data.viewers', 50)
            ->assertJsonPath('meta.language', 'fr');

        $this->getJson('/api/v1/games/7?language=de')
            ->assertOk()
            ->assertJsonPath('data.streams', 0);

        $this->getJson('/api/v1/games/7')->assertJsonPath('data.viewers', 350);
    });

    it('rejects a malformed language code', function () {
        $this->getJson('/api/v1/games?language=FRENCH')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed');
    });
});

describe('GET /api/v1/games/search', function () {
    it('finds games by name, case-insensitively', function () {
        Game::factory()->create(['name' => 'Grand Theft Auto V']);
        Game::factory()->create(['name' => 'Minecraft']);

        $this->getJson('/api/v1/games/search?q=theft')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Grand Theft Auto V');
    });

    it('requires a search term of at least two characters', function (string $query) {
        $this->getJson("/api/v1/games/search{$query}")->assertUnprocessable();
    })->with(['', '?q=', '?q=a']);
});

describe('GET /api/v1/games/{id}/top-streamers', function () {
    beforeEach(function () {
        $game = Game::factory()->create(['id' => '32982']);
        GameSnapshot::factory()->for($game)->create(['collected_at' => '2026-11-24 14:00:00']);
        $snapshot = snapshotWithLanguages($game, ['en' => [3, 600], 'fr' => [2, 90]]);

        streamIn($snapshot, 'third', 100, 'en');
        streamIn($snapshot, 'first', 400, 'en');
        streamIn($snapshot, 'french', 60, 'fr');
        streamIn($snapshot, 'second', 200, 'en');
        streamIn($snapshot, 'french2', 30, 'fr');
    });

    it('ranks the live streams of the latest snapshot', function () {
        $this->getJson('/api/v1/games/32982/top-streamers?limit=5')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0', [
                'rank' => 1,
                'login' => 'first',
                'display_name' => 'First',
                'viewers' => 400,
                'title' => 'Live first',
                'language' => 'en',
                'started_at' => '2026-11-24T12:00:00Z',
            ])
            ->assertJsonPath('data.1.login', 'second')
            ->assertJsonPath('meta.game_id', '32982')
            ->assertJsonPath('meta.collected_at', '2026-11-24T14:05:00Z');
    });

    it('filters by a tracked language', function () {
        $this->getJson('/api/v1/games/32982/top-streamers?language=fr')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.login', 'french')
            ->assertJsonPath('data.1.rank', 2);
    });

    it('rejects untracked languages and other limits', function (string $query) {
        $this->getJson("/api/v1/games/32982/top-streamers?{$query}")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed');
    })->with(['language=de', 'limit=3', 'limit=20']);

    it('returns 404 for an unknown game', function () {
        $this->getJson('/api/v1/games/1/top-streamers')->assertNotFound();
    });
});
