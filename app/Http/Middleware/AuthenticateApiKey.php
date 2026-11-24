<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Exige l'en-tête "Authorization: Bearer <clé>" avec une clé d'API active. */
class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainKey = $request->bearerToken();
        $apiKey = $plainKey !== null ? ApiKey::findActive($plainKey) : null;

        if ($apiKey === null) {
            return response()->json([
                'error' => [
                    'code' => 'unauthenticated',
                    'message' => 'A valid API key is required: Authorization: Bearer <key>.',
                ],
            ], 401);
        }

        $apiKey->markAsUsed();

        return $next($request);
    }
}
