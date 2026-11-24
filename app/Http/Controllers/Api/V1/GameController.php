<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListGamesRequest;
use App\Http\Resources\GameStatsResource;
use App\Models\Game;
use App\Models\GameSnapshot;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

class GameController extends Controller
{
    /** GET /api/v1/games : jeux du dernier cycle, triés et paginés. */
    public function index(ListGamesRequest $request): AnonymousResourceCollection
    {
        $collectedAt = GameSnapshot::latestCollectedAt();

        if ($collectedAt === null) {
            return GameStatsResource::collection([])
                ->additional(['meta' => ['collected_at' => null]]);
        }

        $snapshots = GameSnapshot::query()
            ->with('game')
            ->collectedAt($collectedAt)
            ->orderByDesc($request->sortColumn())
            ->orderBy('game_id')
            ->paginate($request->limit())
            ->withQueryString();

        return GameStatsResource::collection($snapshots)
            ->additional(['meta' => ['collected_at' => Carbon::parse($collectedAt)->toIso8601ZuluString()]]);
    }

    /** GET /api/v1/games/{game} : statistiques actuelles d'un jeu. */
    public function show(Game $game): GameStatsResource
    {
        // Route model binding : Laravel a déjà chargé le jeu, ou répondu 404.
        $snapshot = $game->latestSnapshot;

        abort_if($snapshot === null, 404, 'No statistics collected for this game yet.');

        $snapshot->setRelation('game', $game);

        return (new GameStatsResource($snapshot))
            ->additional(['meta' => ['collected_at' => $snapshot->collected_at->toIso8601ZuluString()]]);
    }
}
