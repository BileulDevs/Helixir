<?php

namespace App\Services\Collector;

use Illuminate\Support\Carbon;

/** Bilan d'un cycle de collecte. */
final readonly class CollectionResult
{
    /** @param list<string> $failedGameIds */
    public function __construct(
        public Carbon $collectedAt,
        public int $collectedGames,
        public array $failedGameIds,
    ) {}

    public function succeeded(): bool
    {
        return $this->collectedGames > 0;
    }
}
