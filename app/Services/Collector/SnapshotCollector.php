<?php

namespace App\Services\Collector;

use App\Jobs\CollectGameStats;
use App\Models\CollectionRun;
use App\Models\Game;
use App\Services\Twitch\TwitchClient;
use Illuminate\Support\Facades\Bus;
use RuntimeException;

/**
 * Lance un cycle de collecte : lit les jeux populaires, puis envoie un job par jeu dans
 * la file. Les workers les traitent en parallèle ; quand le dernier se termine, le cycle
 * est marqué complet et devient visible dans l'API.
 */
class SnapshotCollector
{
    private const BOX_ART_SIZE = ['{width}' => '285', '{height}' => '380'];

    /** Un cycle non terminé depuis plus longtemps est considéré comme abandonné. */
    private const RUN_TIMEOUT_MINUTES = 10;

    public function __construct(private readonly TwitchClient $twitch) {}

    public function collect(): CollectionResult
    {
        $running = CollectionRun::query()
            ->inProgress()
            ->where('created_at', '>', now()->subMinutes(self::RUN_TIMEOUT_MINUTES))
            ->first();

        if ($running !== null) {
            return CollectionResult::skipped($running->collected_at);
        }

        $collectedAt = now()->startOfMinute();
        $games = $this->twitch->topGames(config('helixir.collector.games_limit'));

        if ($games === []) {
            throw new RuntimeException('Twitch returned no games.');
        }

        $this->saveGames($games);

        $run = CollectionRun::create(['collected_at' => $collectedAt, 'games_count' => count($games)]);
        $runId = $run->id;

        Bus::batch(array_map(
            fn (array $game) => new CollectGameStats($game['id'], $collectedAt->toIso8601String()),
            $games,
        ))
            ->name("collect {$collectedAt->toIso8601ZuluString()}")
            ->allowFailures()
            // Exécuté par le worker qui termine le dernier job du cycle.
            ->finally(fn () => CollectionRun::find($runId)?->markCompleted())
            ->dispatch();

        return CollectionResult::dispatched($collectedAt, count($games));
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
}
