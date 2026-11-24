<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\GameSnapshot;
use Illuminate\Database\Seeder;

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
                GameSnapshot::create([
                    'game_id' => $id,
                    'collected_at' => $collectedAt,
                    'streams_count' => (int) round($streams * $drift),
                    'viewers_count' => (int) round($viewers * $drift),
                ]);
            }
        }
    }
}
