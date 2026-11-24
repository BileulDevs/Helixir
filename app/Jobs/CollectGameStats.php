<?php

namespace App\Jobs;

use App\Services\Collector\GameStatsCollector;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Collecte un jeu pour un cycle donné. Plusieurs workers exécutent ces jobs en parallèle.
 */
class CollectGameStats implements ShouldQueue
{
    use Batchable, Queueable;

    /**
     * Les erreurs HTTP sont déjà réessayées par le client Twitch : relancer le job entier
     * repartirait de la première page, on ne le fait donc pas.
     */
    public int $tries = 1;

    /** Le plus gros jeu (plus de 200 pages) plus d'éventuelles pauses de limitation de débit. */
    public int $timeout = 300;

    public function __construct(
        public readonly string $gameId,
        public readonly string $collectedAt,
    ) {}

    public function handle(GameStatsCollector $collector): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        try {
            $collector->collect($this->gameId, Carbon::parse($this->collectedAt));
        } catch (Throwable $e) {
            // Un jeu en échec ne bloque pas les autres : il manquera simplement à ce cycle.
            Log::warning('Twitch collection failed for a game', [
                'game_id' => $this->gameId,
                'collected_at' => $this->collectedAt,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
