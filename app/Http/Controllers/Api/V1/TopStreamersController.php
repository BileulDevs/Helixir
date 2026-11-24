<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\TopStreamersRequest;
use App\Http\Resources\TopStreamerCollection;
use App\Models\Game;

class TopStreamersController extends Controller
{
    /**
     * Top des streamers d'un jeu.
     *
     * Les streams en direct les plus regardés lors du dernier snapshot du jeu.
     * Le filtre `language` n'accepte que les langues suivies par le collecteur.
     */
    public function __invoke(TopStreamersRequest $request, Game $game): TopStreamerCollection
    {
        $snapshot = $game->latestSnapshot;

        abort_if($snapshot === null, 404, 'No statistics collected for this game yet.');

        $streams = $snapshot->streams()
            ->with('streamer')
            ->when($request->language(), fn ($query, string $language) => $query->where('language', $language))
            ->orderByDesc('viewers')
            ->limit($request->limit())
            ->get();

        return (new TopStreamerCollection($streams))->additional(['meta' => [
            'game_id' => $game->id,
            'language' => $request->language(),
            'collected_at' => $snapshot->collected_at->toIso8601ZuluString(),
        ]]);
    }
}
