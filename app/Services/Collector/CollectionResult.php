<?php

namespace App\Services\Collector;

use Illuminate\Support\Carbon;

/** Bilan du lancement d'un cycle de collecte. */
final readonly class CollectionResult
{
    private function __construct(
        public Carbon $collectedAt,
        public int $dispatchedGames,
        public bool $skipped,
    ) {}

    public static function dispatched(Carbon $collectedAt, int $games): self
    {
        return new self($collectedAt, $games, false);
    }

    /** Un cycle précédent tourne encore : on n'en lance pas un deuxième. */
    public static function skipped(Carbon $runningCollectedAt): self
    {
        return new self($runningCollectedAt, 0, true);
    }
}
