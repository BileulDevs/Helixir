<?php

return [

    'twitch' => [
        'client_id' => env('TWITCH_CLIENT_ID'),
        'client_secret' => env('TWITCH_CLIENT_SECRET'),
        'auth_url' => 'https://id.twitch.tv/oauth2/token',
        'api_url' => 'https://api.twitch.tv/helix',

        // En dessous de ce nombre de points restants, on attend la remise à zéro.
        'rate_limit_reserve' => (int) env('TWITCH_RATE_LIMIT_RESERVE', 10),
    ],

    'collector' => [
        // Nombre de jeux suivis (lus dans GET /games/top).
        'games_limit' => (int) env('HELIXIR_GAMES_LIMIT', 100),

        // Pages de 100 streams lues au plus par jeu ; au-delà, le comptage est une estimation.
        'max_pages' => (int) env('HELIXIR_MAX_PAGES', 10),

        // Taille des tops de streamers conservés à chaque snapshot.
        'top_streams' => 10,

        // Langues pour lesquelles on garde aussi un top dédié (filtre ?language= des tops).
        'tracked_languages' => array_values(array_filter(explode(',', env('HELIXIR_TRACKED_LANGUAGES', 'fr,en')))),
    ],

];
