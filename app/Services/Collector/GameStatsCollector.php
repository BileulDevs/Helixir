<?php

namespace App\Services\Collector;

use App\Models\GameLanguageStat;
use App\Models\GameSnapshot;
use App\Models\Streamer;
use App\Models\StreamSnapshot;
use App\Services\Twitch\TwitchClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Collecte d'un seul jeu : parcourt ses streams sur Twitch, puis enregistre son snapshot
 * (totaux, répartition par langue, tops) en une transaction.
 */
class GameStatsCollector
{
    public function __construct(private readonly TwitchClient $twitch) {}

    public function collect(string $gameId, Carbon $collectedAt): void
    {
        $config = config('helixir.collector');
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

        // Curseur encore présent : il restait des pages, le comptage est une estimation.
        $isEstimate = $cursor !== null;

        DB::transaction(fn () => $this->save($gameId, $collectedAt, $aggregate, $isEstimate));
    }

    private function save(string $gameId, Carbon $collectedAt, StreamsAggregate $aggregate, bool $isEstimate): void
    {
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
