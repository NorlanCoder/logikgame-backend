<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — LOGIK GAME
|--------------------------------------------------------------------------
|
| Prefix: /api
| Middleware par défaut : throttle:api
|
| Structure :
|   - /admin/*   → Routes administrateur (auth:sanctum, guard:admin)
|   - /player/*  → Routes joueur (auth par token d'accès session)
|   - /game/*    → Routes publiques du jeu (projection, inscription)
|
*/

// --- Santé de l'API ---
Route::get('/ping', fn () => response()->json(['status' => 'ok', 'timestamp' => now()->toIso8601String()]));

// --- Auth Admin ---
Route::prefix('admin')->group(function () {
    // Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', function (Request $request) {
            return $request->user();
        });
        // Route::post('/logout', [AuthController::class, 'logout']);

        // Sessions CRUD
        // Route::apiResource('sessions', SessionController::class);

        // Manches, Questions, etc. seront ajoutées ici
    });
});

// --- Routes Joueur ---
Route::prefix('player')->group(function () {
    // Inscription publique
    // Route::post('/register', [RegistrationController::class, 'store']);

    // Routes authentifiées par access_token
    // Route::middleware('auth:player')->group(function () {
    //     Route::get('/session', [GameController::class, 'session']);
    //     Route::post('/answer', [GameController::class, 'submitAnswer']);
    // });
});

// --- Routes Publiques (Projection, etc.) ---
Route::prefix('game')->group(function () {
    // Route::get('/projection/{code}', [ProjectionController::class, 'show']);
    // Route::get('/sessions/{session}/status', [GameStatusController::class, 'show']);
});
