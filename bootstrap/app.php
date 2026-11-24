<?php

use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\CacheStatsResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'api.key' => AuthenticateApiKey::class,
            'cache.stats' => CacheStatsResponse::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Format d'erreur unique de l'API : { "error": { "code", "message" } }.
        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => 'validation_failed',
                    'message' => $e->getMessage(),
                    'fields' => $e->errors(),
                ],
            ], 422);
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            // Les erreurs de route model binding arrivent avec le message interne
            // d'Eloquent : on le remplace par un message public.
            $message = $e->getPrevious() instanceof ModelNotFoundException || $e->getMessage() === ''
                ? 'Resource not found.'
                : $e->getMessage();

            return response()->json([
                'error' => ['code' => 'not_found', 'message' => $message],
            ], 404);
        });

        // Quota dépassé : on garde les en-têtes Retry-After et X-RateLimit-*.
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'error' => ['code' => 'rate_limited', 'message' => 'Too many requests, retry later.'],
            ], 429, $e->getHeaders());
        });
    })->create();
