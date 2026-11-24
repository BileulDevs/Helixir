<?php

namespace App\Console\Commands;

use App\Services\Collector\SnapshotCollector;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('helixir:collect')]
#[Description('Start a collection cycle: one queued job per top Twitch game')]
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

        $cycle = $result->collectedAt->toIso8601ZuluString();

        if ($result->skipped) {
            $this->warn("Cycle {$cycle} is still running: no new cycle started.");

            return self::SUCCESS;
        }

        $this->info("Cycle {$cycle}: {$result->dispatchedGames} games queued for collection.");

        return self::SUCCESS;
    }
}
