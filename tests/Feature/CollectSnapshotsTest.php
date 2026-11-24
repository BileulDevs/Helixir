<?php

use App\Jobs\CollectGameStats;
use App\Models\CollectionRun;
use App\Models\Game;
use App\Models\GameLanguageStat;
use App\Models\GameSnapshot;
use App\Models\StreamSnapshot;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'helixir.twitch.client_id' => 'test-client',
        'helixir.twitch.client_secret' => 'test-secret',
        'helixir.collector.games_limit' => 2,
        'helixir.collector.max_pages' => 3,
        'helixir.collector.tracked_languages' => ['fr'],
    ]);
    Sleep::fake();
});

function twitchStream(string $id, int $viewers, string $language = 'en'): array
{
    return [
        'id' => "s{$id}", 'user_id' => "u{$id}", 'user_login' => "user{$id}", 'user_name' => "User{$id}",
        'viewer_count' => $viewers, 'language' => $language, 'title' => "Stream {$id}",
        'started_at' => '2026-11-24T12:00:00Z',
    ];
}

/** Faux Twitch : 2 jeux, le premier sur deux pages, le second sur une page. */
function fakeTwitch(array $overrides = []): void
{
    Http::fake(fn (Request $request) => match (true) {
        str_contains($request->url(), 'id.twitch.tv') => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
        str_contains($request->url(), 'games/top') => Http::response([
            'data' => [
                ['id' => '1', 'name' => 'Game One', 'box_art_url' => 'https://img/{width}x{height}.jpg'],
                ['id' => '', 'name' => 'No id'],
                ['id' => '2', 'name' => 'Game Two', 'box_art_url' => ''],
            ],
            'pagination' => [],
        ]),
        isset($overrides[$request['game_id']]) => $overrides[$request['game_id']]($request),
        $request['game_id'] === '1' && ! isset($request['after']) => Http::response([
            'data' => [twitchStream('1', 300), twitchStream('2', 200, 'fr')],
            'pagination' => ['cursor' => 'next'],
        ]),
        $request['game_id'] === '1' => Http::response(['data' => [twitchStream('3', 100, 'fr')], 'pagination' => []]),
        default => Http::response(['data' => [twitchStream('9', 50)], 'pagination' => []]),
    });
}

it('stores one snapshot per game with language stats and top streams', function () {
    fakeTwitch();

    $this->artisan('helixir:collect')->assertSuccessful();

    expect(Game::find('1'))->name->toBe('Game One')
        ->box_art_url->toBe('https://img/285x380.jpg')
        ->and(Game::count())->toBe(2);

    $snapshot = GameSnapshot::where('game_id', '1')->sole();
    expect($snapshot)
        ->streams_count->toBe(3)
        ->viewers_count->toBe(600)
        ->is_estimate->toBeFalse();

    expect(GameLanguageStat::where('game_snapshot_id', $snapshot->id)->where('language', 'fr')->sole())
        ->streams_count->toBe(2)
        ->viewers_count->toBe(300);

    expect(StreamSnapshot::where('game_snapshot_id', $snapshot->id)->count())->toBe(3);
});

it('sends the app token and client id to Helix', function () {
    fakeTwitch();

    $this->artisan('helixir:collect');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'api.twitch.tv')
        && $request->hasHeader('Authorization', 'Bearer token')
        && $request->hasHeader('Client-Id', 'test-client'));
});

it('marks the count as an estimate when the page cap is reached', function () {
    config(['helixir.collector.max_pages' => 1]);
    fakeTwitch();

    $this->artisan('helixir:collect')->assertSuccessful();

    expect(GameSnapshot::where('game_id', '1')->sole()->is_estimate)->toBeTrue();
});

it('keeps collecting other games when one fails', function () {
    fakeTwitch(['2' => fn () => Http::response([], 500)]);

    $this->artisan('helixir:collect')->assertSuccessful();

    expect(GameSnapshot::pluck('game_id')->all())->toBe(['1'])
        ->and(CollectionRun::sole()->completed_at)->not->toBeNull();
});

it('queues one job per game in a single batch', function () {
    Bus::fake();
    fakeTwitch();

    $this->artisan('helixir:collect')
        ->expectsOutputToContain('2 games queued')
        ->assertSuccessful();

    Bus::assertBatched(fn (PendingBatch $batch) => $batch->jobs->count() === 2
        && $batch->jobs->every(fn ($job) => $job instanceof CollectGameStats));
    expect(CollectionRun::sole())->games_count->toBe(2)->completed_at->toBeNull();
});

it('marks the cycle completed once every game job has run', function () {
    fakeTwitch();

    $this->artisan('helixir:collect')->assertSuccessful();

    $run = CollectionRun::sole();
    expect($run->completed_at)->not->toBeNull()
        ->and(GameSnapshot::where('collected_at', $run->collected_at)->count())->toBe(2);
});

it('does not start a cycle while the previous one is still running', function () {
    CollectionRun::create(['collected_at' => now()->subMinutes(2), 'games_count' => 100]);
    fakeTwitch();

    $this->artisan('helixir:collect')
        ->expectsOutputToContain('still running')
        ->assertSuccessful();

    expect(CollectionRun::count())->toBe(1);
    Http::assertNothingSent();
});

it('starts a new cycle when the running one is abandoned', function () {
    CollectionRun::create(['collected_at' => now()->subMinutes(30), 'games_count' => 100]);
    CollectionRun::query()->update(['created_at' => now()->subMinutes(30)]);
    fakeTwitch();

    $this->artisan('helixir:collect')->assertSuccessful();

    expect(CollectionRun::count())->toBe(2);
});

it('refreshes the token once when Twitch answers 401', function () {
    $calls = 0;
    fakeTwitch(['2' => function () use (&$calls) {
        return ++$calls === 1
            ? Http::response([], 401)
            : Http::response(['data' => [twitchStream('9', 50)], 'pagination' => []]);
    }]);

    $this->artisan('helixir:collect')->assertSuccessful();

    expect(GameSnapshot::where('game_id', '2')->exists())->toBeTrue();
    Http::assertSentCount(7); // 2 tokens, 1 top, 2 pages jeu 1, 2 appels jeu 2
});

it('fails clearly without Twitch credentials', function () {
    config(['helixir.twitch.client_id' => null]);
    Http::fake();

    $this->artisan('helixir:collect')
        ->expectsOutputToContain('TWITCH_CLIENT_ID')
        ->assertFailed();

    Http::assertNothingSent();
});
