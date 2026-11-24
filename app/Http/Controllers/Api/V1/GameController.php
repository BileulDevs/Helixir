<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListGamesRequest;
use App\Http\Requests\ShowGameRequest;
use App\Http\Resources\GameStatsResource;
use App\Http\Support\StatsResponseCache;
use App\Models\Game;
use App\Models\GameSnapshot;
use App\Queries\GameStatsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

class GameController extends Controller
{
    public function __construct(private readonly GameStatsQuery $stats) {}

    /** GET /api/v1/games : jeux du dernier cycle, triés et paginés, éventuellement pour une langue. */
    public function index(ListGamesRequest $request, StatsResponseCache $cache): JsonResponse|AnonymousResourceCollection
    {
        $collectedAt = GameSnapshot::latestCollectedAt();

        if ($collectedAt === null) {
            return GameStatsResource::collection([])
                ->additional(['meta' => ['collected_at' => null]]);
        }

        return $cache->remember($request, $collectedAt, $request->validated(), function () use ($request, $collectedAt) {
            $snapshots = $this->stats
                ->forCycle($collectedAt, $request->language(), $request->sortColumn())
                ->paginate($request->limit())
                ->withQueryString();

            return GameStatsResource::collection($snapshots)->additional(['meta' => [
                'language' => $request->language(),
                'collected_at' => Carbon::parse($collectedAt)->toIso8601ZuluString(),
            ]]);
        });
    }

    /** GET /api/v1/games/{game} : statistiques actuelles d'un jeu. */
    public function show(ShowGameRequest $request, Game $game): GameStatsResource
    {
        // Route model binding : Laravel a déjà chargé le jeu, ou répondu 404.
        $snapshot = $this->stats->latestForGame($game, $request->language());

        abort_if($snapshot === null, 404, 'No statistics collected for this game yet.');

        return (new GameStatsResource($snapshot->setRelation('game', $game)))->additional(['meta' => [
            'language' => $request->language(),
            'collected_at' => $snapshot->collected_at->toIso8601ZuluString(),
        ]]);
    }
}
