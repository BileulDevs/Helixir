<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchGamesRequest;
use App\Http\Resources\GameResource;
use App\Models\Game;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GameSearchController extends Controller
{
    /** GET /api/v1/games/search?q= : recherche d'un jeu par nom, insensible à la casse. */
    public function __invoke(SearchGamesRequest $request): AnonymousResourceCollection
    {
        $games = Game::query()
            ->whereLike('name', '%'.$request->term().'%', caseSensitive: false)
            ->orderBy('name')
            ->limit(SearchGamesRequest::MAX_RESULTS)
            ->get();

        return GameResource::collection($games);
    }
}
