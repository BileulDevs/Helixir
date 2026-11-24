<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListGamesRequest;
use App\Http\Requests\ShowGameRequest;
use App\Http\Resources\GameStatsResource;
use App\Models\Game;
use App\Models\GameSnapshot;
use App\Queries\GameStatsQuery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class GameController extends Controller
{
    public function __construct(private readonly GameStatsQuery $stats) {}

    /**
     * Liste des jeux.
     *
     * Jeux du dernier cycle de collecte avec leur nombre de streams, de spectateurs et la moyenne
     * par stream. Avec `language`, les chiffres ne concernent que les streams dans cette langue.
     * `is_estimate` vaut `true` quand le jeu dépasse le plafond de pages lu sur Twitch.
     */
    public function index(ListGamesRequest $request): AnonymousResourceCollection
    {
        $collectedAt = GameSnapshot::latestCollectedAt();

        // Base vide : même forme de réponse, avec une liste vide.
        $snapshots = $collectedAt === null
            ? new LengthAwarePaginator([], 0, $request->limit(), 1, ['path' => $request->url()])
            : $this->stats
                ->forCycle($collectedAt, $request->language(), $request->sortColumn())
                ->paginate($request->limit())
                ->withQueryString();

        return GameStatsResource::collection($snapshots)->additional(['meta' => [
            'language' => $request->language(),
            'collected_at' => $collectedAt !== null ? Carbon::parse($collectedAt)->toIso8601ZuluString() : null,
        ]]);
    }

    /**
     * Statistiques d'un jeu.
     *
     * Dernier snapshot connu du jeu. Avec `language`, les chiffres ne concernent que les streams
     * dans cette langue (0 si personne ne la stream).
     */
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
