<?php

namespace App\Queries;

use App\Models\Game;
use App\Models\GameSnapshot;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lecture des statistiques de jeux, globales ou pour une langue.
 * Pour une langue, les compteurs de game_language_stats remplacent ceux du snapshot :
 * la même API Resource sert donc dans les deux cas.
 */
class GameStatsQuery
{
    /** @return Builder<GameSnapshot> */
    public function forCycle(string $collectedAt, ?string $language, string $sortColumn): Builder
    {
        $query = GameSnapshot::query()
            ->with('game')
            ->collectedAt($collectedAt);

        if ($language === null) {
            return $query
                ->orderByDesc("game_snapshots.{$sortColumn}")
                ->orderBy('game_snapshots.game_id');
        }

        return $query
            ->join('game_language_stats as gls', 'gls.game_snapshot_id', '=', 'game_snapshots.id')
            ->where('gls.language', $language)
            ->select([
                'game_snapshots.id',
                'game_snapshots.game_id',
                'game_snapshots.collected_at',
                'game_snapshots.is_estimate',
                'gls.streams_count',
                'gls.viewers_count',
            ])
            ->orderByDesc("gls.{$sortColumn}")
            ->orderBy('game_snapshots.game_id');
    }

    /** Dernier snapshot d'un jeu, éventuellement réduit à une langue (0 si personne ne la stream). */
    public function latestForGame(Game $game, ?string $language): ?GameSnapshot
    {
        $snapshot = $game->latestSnapshot;

        if ($snapshot === null || $language === null) {
            return $snapshot;
        }

        $stat = $snapshot->languageStats()->where('language', $language)->first();

        // Copie en mémoire : le snapshot d'origine reste intact.
        return $snapshot->replicate()
            ->forceFill([
                'streams_count' => $stat?->streams_count ?? 0,
                'viewers_count' => $stat?->viewers_count ?? 0,
            ])
            ->setRelation('game', $game);
    }
}
