<?php

return [

    'twitch' => [
        'client_id' => env('TWITCH_CLIENT_ID'),
        'client_secret' => env('TWITCH_CLIENT_SECRET'),
        'auth_url' => 'https://id.twitch.tv/oauth2/token',
        'api_url' => 'https://api.twitch.tv/helix',

        // En dessous de ce nombre de points restants, on attend la remise à zéro. La marge
        // couvre les requêtes déjà parties des autres workers.
        'rate_limit_reserve' => (int) env('TWITCH_RATE_LIMIT_RESERVE', 40),
    ],

    'api' => [
        // Requêtes autorisées par minute et par clé d'API.
        'rate_limit_per_minute' => (int) env('HELIXIR_RATE_LIMIT', 60),

        // Durée de cache des réponses de /games et /top-streamers.
        'cache_ttl_seconds' => (int) env('HELIXIR_CACHE_TTL', 60),
    ],

    'collector' => [
        // Nombre de jeux suivis (lus dans GET /games/top).
        'games_limit' => (int) env('HELIXIR_GAMES_LIMIT', 100),

        // Tous les streams d'un jeu sont lus. Ce nombre de pages de 100 streams n'est qu'un
        // garde-fou contre une pagination anormale (400 pages = 40 000 streams) ; s'il est
        // atteint, le comptage est marqué comme estimation.
        'max_pages' => (int) env('HELIXIR_MAX_PAGES', 400),

        // Taille des tops de streamers conservés à chaque snapshot.
        'top_streams' => 10,

        // Langues pour lesquelles on garde aussi un top dédié (filtre ?language= des tops).
        'tracked_languages' => array_values(array_filter(explode(',', env('HELIXIR_TRACKED_LANGUAGES', 'fr,en')))),
    ],

];
