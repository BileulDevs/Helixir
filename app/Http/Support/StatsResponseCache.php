<?php

namespace App\Http\Support;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Cache;

/**
 * Met en cache le JSON des endpoints les plus consultés.
 *
 * La date du cycle de collecte fait partie de la clé : dès qu'un nouveau snapshot
 * existe, les requêtes utilisent de nouvelles clés, donc l'ancien cache n'est plus lu
 * (il expire tout seul après le TTL).
 */
class StatsResponseCache
{
    /**
     * @param  array<string, mixed>  $params  Paramètres validés uniquement, pour qu'un paramètre
     *                                        inconnu ne crée pas une nouvelle entrée de cache.
     * @param  Closure(): JsonResource  $build
     */
    public function remember(Request $request, string $collectedAt, array $params, Closure $build): JsonResponse
    {
        ksort($params);
        $key = 'api:'.sha1($request->path().'?'.http_build_query($params).'@'.$collectedAt);

        $data = Cache::remember(
            $key,
            config('helixir.api.cache_ttl_seconds'),
            fn () => $build()->response($request)->getData(true),
        );

        return response()->json($data);
    }
}
