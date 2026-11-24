<?php

namespace Database\Seeders;

use App\Models\CollectionRun;
use App\Models\Game;
use App\Models\GameLanguageStat;
use App\Models\GameSnapshot;
use App\Models\Streamer;
use App\Models\StreamSnapshot;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Données de démonstration : permet de tester l'API sans clé Twitch.
 * Idempotent : peut être relancé sans créer de doublons.
 */
class GameSeeder extends Seeder
{
    /** [id Twitch, nom, streams, spectateurs] au dernier cycle. */
    private const GAMES = [
        ['509658', 'Just Chatting', 4200, 410000],
        ['21779', 'League of Legends', 2900, 180000],
        ['32982', 'Grand Theft Auto V', 3100, 165000],
        ['516575', 'VALORANT', 3800, 95000],
        ['32399', 'Counter-Strike', 2100, 120000],
        ['27471', 'Minecraft', 5200, 70000],
        ['33214', 'Fortnite', 6100, 64000],
        ['512710', 'Call of Duty: Warzone', 2600, 52000],
    ];

    /** Part de chaque langue dans les streams et les spectateurs d'un jeu. */
    private const LANGUAGE_SHARES = ['en' => 0.5, 'fr' => 0.15, 'es' => 0.2, 'other' => 0.15];

    private const STREAMERS_PER_LANGUAGE = 5;

    private const CYCLES = 3;

    private const MINUTES_BETWEEN_CYCLES = 5;

    public function run(): void
    {
        foreach (self::GAMES as [$id, $name]) {
            Game::updateOrCreate(['id' => $id], ['name' => $name]);
        }

        if (GameSnapshot::query()->exists()) {
            return;
        }

        $lastCycle = now()->startOfMinute();

        // Plusieurs cycles passés pour que "le dernier snapshot" ait un sens.
        for ($cycle = self::CYCLES - 1; $cycle >= 0; $cycle--) {
            $collectedAt = $lastCycle->copy()->subMinutes($cycle * self::MINUTES_BETWEEN_CYCLES);
            $drift = 1 - $cycle * 0.03;

            foreach (self::GAMES as [$id, , $streams, $viewers]) {
                $this->seedSnapshot($id, $collectedAt, (int) round($streams * $drift), (int) round($viewers * $drift));
            }

            CollectionRun::create([
                'collected_at' => $collectedAt,
                'games_count' => count(self::GAMES),
                'completed_at' => $collectedAt,
            ]);
        }
    }

    private function seedSnapshot(string $gameId, Carbon $collectedAt, int $streams, int $viewers): void
    {
        $snapshot = GameSnapshot::create([
            'game_id' => $gameId,
            'collected_at' => $collectedAt,
            'streams_count' => $streams,
            'viewers_count' => $viewers,
        ]);

        foreach (self::LANGUAGE_SHARES as $language => $share) {
            $languageViewers = (int) round($viewers * $share);

            GameLanguageStat::create([
                'game_snapshot_id' => $snapshot->id,
                'language' => $language,
                'streams_count' => (int) round($streams * $share),
                'viewers_count' => $languageViewers,
            ]);

            $this->seedTopStreams($snapshot, $language, $languageViewers);
        }
    }

    /** Quelques streamers fictifs par langue, du plus regardé au moins regardé. */
    private function seedTopStreams(GameSnapshot $snapshot, string $language, int $languageViewers): void
    {
        for ($rank = 1; $rank <= self::STREAMERS_PER_LANGUAGE; $rank++) {
            $login = "demo_{$language}_{$snapshot->game_id}_{$rank}";

            $streamer = Streamer::updateOrCreate(
                ['id' => "demo-{$language}-{$snapshot->game_id}-{$rank}"],
                ['login' => $login, 'display_name' => $login],
            );

            StreamSnapshot::create([
                'game_snapshot_id' => $snapshot->id,
                'streamer_id' => $streamer->id,
                'viewers' => (int) round($languageViewers * 0.2 / $rank),
                'title' => "Demo stream #{$rank}",
                'language' => $language,
                'started_at' => $snapshot->collected_at->copy()->subHours($rank),
            ]);
        }
    }
}
