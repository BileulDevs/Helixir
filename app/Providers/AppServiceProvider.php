<?php

namespace App\Providers;

use App\Services\Twitch\TwitchClient;
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
        //
    }
}
