<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\TopStreamersRequest;
use App\Http\Resources\TopStreamerCollection;
use App\Http\Support\StatsResponseCache;
use App\Models\Game;
use Illuminate\Http\JsonResponse;

class TopStreamersController extends Controller
{
    /** GET /api/v1/games/{game}/top-streamers : top N des streams en direct du dernier snapshot. */
    public function __invoke(TopStreamersRequest $request, Game $game, StatsResponseCache $cache): JsonResponse
    {
        $snapshot = $game->latestSnapshot;

        abort_if($snapshot === null, 404, 'No statistics collected for this game yet.');

        return $cache->remember($request, $snapshot->collected_at->toIso8601ZuluString(), $request->validated(), function () use ($request, $game, $snapshot) {
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
        });
    }
}
