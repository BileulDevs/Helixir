<?php

namespace App\Providers;

use App\Services\Twitch\TwitchClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Le client n'est construit (et la config vérifiée) que lorsqu'on en a besoin.
        $this->app->bind(TwitchClient::class, fn () => TwitchClient::fromConfig());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Quota par clé d'API. Laravel exécute "throttle" avant nos propres middlewares,
        // donc on compte sur l'empreinte du token reçu (ou l'IP sans token) : les essais
        // de clés invalides sont aussi limités.
        RateLimiter::for('api-key', function (Request $request) {
            $token = $request->bearerToken();

            return Limit::perMinute(config('helixir.api.rate_limit_per_minute'))
                ->by($token !== null ? 'key:'.hash('sha256', $token) : "ip:{$request->ip()}");
        });
    }
}
