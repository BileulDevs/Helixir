<?php

use App\Http\Controllers\Api\V1\GameController;
use App\Http\Controllers\Api\V1\GameSearchController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\TopStreamersController;
use Illuminate\Support\Facades\Route;

// Toutes les routes de ce fichier sont déjà préfixées par /api (bootstrap/app.php).
Route::prefix('v1')->group(function () {
    Route::get('/health', HealthController::class);

    Route::get('/games', [GameController::class, 'index']);
    // Déclarée avant /games/{game}, sinon "search" serait pris pour un identifiant.
    Route::get('/games/search', GameSearchController::class);
    Route::get('/games/{game}', [GameController::class, 'show']);
    Route::get('/games/{game}/top-streamers', TopStreamersController::class);
});
