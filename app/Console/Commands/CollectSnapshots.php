<?php

namespace App\Console\Commands;

use App\Services\Collector\SnapshotCollector;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('helixir:collect')]
#[Description('Collect a snapshot of live Twitch stats for the top games')]
class CollectSnapshots extends Command
{
    public function handle(): int
    {
        try {
            // Résolu ici (et non injecté) pour que l'absence de clés Twitch soit gérée par le catch.
            $result = $this->laravel->make(SnapshotCollector::class)->collect();
        } catch (Throwable $e) {
            $this->error("Collection failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info("Snapshot {$result->collectedAt->toIso8601ZuluString()}: {$result->collectedGames} games collected.");

        if ($result->failedGameIds !== []) {
            $this->warn('Failed games: '.implode(', ', $result->failedGameIds));
        }

        return $result->succeeded() ? self::SUCCESS : self::FAILURE;
    }
}
