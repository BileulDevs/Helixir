<?php

namespace App\Http\Middleware;

use App\Models\GameSnapshot;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Met en cache le JSON des endpoints les plus consultés.
 * Usage : ->middleware('cache.stats:limit,page,sort,language').
 *
 * La date du dernier cycle de collecte fait partie de la clé : dès qu'un nouveau
 * snapshot existe, les requêtes utilisent de nouvelles clés, donc l'ancien cache
 * n'est plus lu (il expire tout seul après le TTL).
 */
class CacheStatsResponse
{
    /** @param string ...$params Paramètres de requête qui font varier la réponse. */
    public function handle(Request $request, Closure $next, string ...$params): Response
    {
        $collectedAt = GameSnapshot::latestCollectedAt();

        if ($collectedAt === null) {
            return $next($request);
        }

        // Seuls les paramètres déclarés comptent : un paramètre inconnu ne crée pas d'entrée.
        $query = $request->only($params);
        ksort($query);
        $key = 'api:'.sha1($request->path().'?'.http_build_query($query).'@'.$collectedAt);

        $cached = Cache::get($key);

        if (is_array($cached)) {
            return response()->json($cached);
        }

        $response = $next($request);

        // On ne garde que les succès : une erreur 404 ou 422 n'est jamais servie depuis le cache.
        if ($response instanceof JsonResponse && $response->getStatusCode() === 200) {
            Cache::put($key, $response->getData(true), config('helixir.api.cache_ttl_seconds'));
        }

        return $response;
    }
}
