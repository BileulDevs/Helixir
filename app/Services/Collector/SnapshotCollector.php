<?php

namespace App\Services\Collector;

use App\Models\Game;
use App\Models\GameLanguageStat;
use App\Models\GameSnapshot;
use App\Models\Streamer;
use App\Models\StreamSnapshot;
use App\Services\Twitch\TwitchClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Un cycle de collecte : lit Twitch, puis enregistre tous les snapshots du cycle
 * en une seule transaction. L'API ne voit donc jamais un cycle à moitié écrit.
 */
class SnapshotCollector
{
    private const BOX_ART_SIZE = ['{width}' => '285', '{height}' => '380'];

    public function __construct(private readonly TwitchClient $twitch) {}

    public function collect(): CollectionResult
    {
        $collectedAt = now()->startOfMinute();
        $config = config('helixir.collector');

        $games = $this->twitch->topGames($config['games_limit']);
        $this->saveGames($games);

        $aggregates = [];
        $failed = [];

        foreach ($games as $game) {
            try {
                $aggregates[$game['id']] = $this->collectGame($game['id'], $config);
            } catch (Throwable $e) {
                // Un jeu en échec ne bloque pas les autres.
                $failed[] = $game['id'];
                Log::warning('Twitch collection failed for a game', ['game_id' => $game['id'], 'error' => $e->getMessage()]);
            }
        }

        if ($aggregates !== []) {
            DB::transaction(fn () => $this->saveSnapshots($aggregates, $collectedAt));
        }

        return new CollectionResult($collectedAt, count($aggregates), $failed);
    }

    /** @return array{aggregate: StreamsAggregate, is_estimate: bool} */
    private function collectGame(string $gameId, array $config): array
    {
        $aggregate = new StreamsAggregate($config['top_streams'], $config['tracked_languages']);
        $cursor = null;
        $pages = 0;

        do {
            $page = $this->twitch->streamsPage($gameId, $cursor);
            $aggregate->add($page['data']);
            $cursor = $page['cursor'];
            $pages++;
            // On s'arrête quand le curseur disparaît, pas quand une page est courte.
        } while ($cursor !== null && $pages < $config['max_pages']);

        return ['aggregate' => $aggregate, 'is_estimate' => $cursor !== null];
    }

    /** @param list<array{id: string, name: string, box_art_url: string}> $games */
    private function saveGames(array $games): void
    {
        $rows = array_map(fn (array $game) => [
            'id' => $game['id'],
            'name' => $game['name'],
            'box_art_url' => strtr($game['box_art_url'] ?? '', self::BOX_ART_SIZE) ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $games);

        Game::upsert($rows, ['id'], ['name', 'box_art_url', 'updated_at']);
    }

    /** @param array<string, array{aggregate: StreamsAggregate, is_estimate: bool}> $aggregates */
    private function saveSnapshots(array $aggregates, Carbon $collectedAt): void
    {
        foreach ($aggregates as $gameId => ['aggregate' => $aggregate, 'is_estimate' => $isEstimate]) {
            $snapshot = GameSnapshot::create([
                'game_id' => $gameId,
                'collected_at' => $collectedAt,
                'streams_count' => $aggregate->streamsCount(),
                'viewers_count' => $aggregate->viewersCount(),
                'is_estimate' => $isEstimate,
            ]);

            $this->saveLanguageStats($snapshot, $aggregate);
            $this->saveTopStreams($snapshot, $aggregate);
        }
    }

    private function saveLanguageStats(GameSnapshot $snapshot, StreamsAggregate $aggregate): void
    {
        $rows = [];

        foreach ($aggregate->languages() as $language => $stats) {
            $rows[] = [
                'game_snapshot_id' => $snapshot->id,
                'language' => $language,
                'streams_count' => $stats['streams'],
                'viewers_count' => $stats['viewers'],
            ];
        }

        GameLanguageStat::insert($rows);
    }

    private function saveTopStreams(GameSnapshot $snapshot, StreamsAggregate $aggregate): void
    {
        $streams = $aggregate->topStreams();

        if ($streams === []) {
            return;
        }

        Streamer::upsert(array_map(fn (array $stream) => [
            'id' => $stream['user_id'],
            'login' => $stream['user_login'],
            'display_name' => $stream['user_name'],
            'created_at' => now(),
            'updated_at' => now(),
        ], $streams), ['id'], ['login', 'display_name', 'updated_at']);

        StreamSnapshot::insert(array_map(fn (array $stream) => [
            'game_snapshot_id' => $snapshot->id,
            'streamer_id' => $stream['user_id'],
            'viewers' => (int) $stream['viewer_count'],
            'title' => mb_substr($stream['title'], 0, 500),
            'language' => $stream['language'] !== '' ? $stream['language'] : 'other',
            'started_at' => Carbon::parse($stream['started_at']),
        ], $streams));
    }
}
