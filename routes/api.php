<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GameController as AdminGameController;
use App\Http\Controllers\Admin\PreselectionController as AdminPreselectionController;
use App\Http\Controllers\Admin\PreselectionQuestionController;
use App\Http\Controllers\Admin\QuestionController;
use App\Http\Controllers\Admin\QuestionHintController;
use App\Http\Controllers\Admin\SecondChanceQuestionController;
use App\Http\Controllers\Admin\SessionController;
use App\Http\Controllers\Admin\SessionRoundController;
use App\Http\Controllers\Player\GameController as PlayerGameController;
use App\Http\Controllers\Player\PreselectionController;
use App\Http\Controllers\Player\RegistrationController;
use App\Http\Controllers\Player\SessionController as PlayerSessionController;
use App\Http\Controllers\Projection\ProjectionController;
use Illuminate\Support\Facades\Route;

// --- Santé de l'API ---
Route::get('/ping', fn () => response()->json(['status' => 'ok', 'timestamp' => now()->toIso8601String()]));

/*
|--------------------------------------------------------------------------
| Routes Admin
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->group(function () {
    // Authentification (publique)
    Route::post('/login', [AdminAuthController::class, 'login']);

    // Routes protégées par Sanctum
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout']);
        Route::get('/me', [AdminAuthController::class, 'me']);

        // Sessions CRUD
        Route::apiResource('sessions', SessionController::class);

        // Manches d'une session
        Route::prefix('sessions/{session}')->group(function () {
            Route::get('/rounds', [SessionRoundController::class, 'index']);
            Route::patch('/rounds/{round}', [SessionRoundController::class, 'update']);

            // Questions d'une manche
            Route::apiResource('rounds/{round}/questions', QuestionController::class)
                ->except(['index']);
            Route::get('/rounds/{round}/questions', [QuestionController::class, 'index'])->name('admin.rounds.questions.index');
            Route::post('/rounds/{round}/questions/{question}/duplicate', [QuestionController::class, 'duplicate']);

            // Indice d'une question (manche 2)
            Route::prefix('rounds/{round}/questions/{question}')->group(function () {
                Route::get('/hint', [QuestionHintController::class, 'show']);
                Route::put('/hint', [QuestionHintController::class, 'upsert']);
                Route::delete('/hint', [QuestionHintController::class, 'destroy']);

                // Question de seconde chance (manche 3)
                Route::get('/second-chance', [SecondChanceQuestionController::class, 'show']);
                Route::put('/second-chance', [SecondChanceQuestionController::class, 'upsert']);
                Route::delete('/second-chance', [SecondChanceQuestionController::class, 'destroy']);
            });

            // Dashboard live
            Route::get('/dashboard', [DashboardController::class, 'show']);

            // Projection — consulter et générer un code d'accès
            Route::get('/projection', [ProjectionController::class, 'show']);
            Route::post('/projection/generate', [ProjectionController::class, 'generateCode']);

            // Questions de pré-sélection CRUD
            Route::apiResource('preselection-questions', PreselectionQuestionController::class)
                ->parameters(['preselection-questions' => 'preselectionQuestion']);

            // Résultats de pré-sélection (consultation)
            Route::get('/preselection/registrations', [AdminPreselectionController::class, 'registrations']);
            Route::get('/preselection/registrations/{registration}', [AdminPreselectionController::class, 'registrationDetail']);

            // Moteur de jeu
            Route::prefix('game')->group(function () {
                // Phase pré-jeu
                Route::post('/open-registration', [AdminGameController::class, 'openRegistration']);
                Route::post('/close-registration', [AdminGameController::class, 'closeRegistration']);
                Route::post('/open-preselection', [AdminGameController::class, 'openPreselection']);
                Route::post('/select-players', [AdminGameController::class, 'selectPlayers']);
                Route::post('/confirm-selection', [AdminGameController::class, 'confirmSelection']);
                Route::post('/start', [AdminGameController::class, 'startSession']);

                // Cycle de question
                Route::post('/launch-question', [AdminGameController::class, 'launchQuestion']);
                Route::post('/close-question', [AdminGameController::class, 'closeQuestion']);
                Route::post('/reveal-answer', [AdminGameController::class, 'revealAnswer']);

                // Manche 3 — Seconde Chance
                Route::post('/launch-second-chance', [AdminGameController::class, 'launchSecondChance']);
                Route::post('/close-second-chance', [AdminGameController::class, 'closeSecondChance']);
                Route::post('/reveal-second-chance', [AdminGameController::class, 'revealSecondChance']);

                // Manche 5 — Top 4
                Route::post('/finalize-top4', [AdminGameController::class, 'finalizeTop4']);

                // Manches 6/7 — Duels
                Route::post('/assign-duel-questions', [AdminGameController::class, 'assignDuelQuestions']);
                Route::post('/setup-turn-order', [AdminGameController::class, 'setupTurnOrder']);
                Route::get('/next-turn', [AdminGameController::class, 'getNextTurn']);

                // Manche 8 — Finale
                Route::post('/launch-finale-vote', [AdminGameController::class, 'launchFinaleVote']);
                Route::post('/reveal-finale-choices', [AdminGameController::class, 'revealFinaleChoices']);
                Route::post('/resolve-finale', [AdminGameController::class, 'resolveFinale']);

                // Navigation
                Route::post('/next-round', [AdminGameController::class, 'nextRound']);
                Route::post('/end', [AdminGameController::class, 'endSession']);
            });
        });
    });
});

/*
|--------------------------------------------------------------------------
| Routes Joueur
|--------------------------------------------------------------------------
*/
Route::prefix('player')->group(function () {
    // Sessions ouvertes (publique)
    Route::get('/sessions', [PlayerSessionController::class, 'index']);
    Route::get('/sessions/{session}', [PlayerSessionController::class, 'show']);

    // Inscription (publique)
    Route::post('/register', [RegistrationController::class, 'store']);
    Route::get('/registrations/{registration}', [RegistrationController::class, 'show']);

    // Pré-sélection (publique)
    Route::get('/preselection/verify', [PreselectionController::class, 'verify']);
    Route::get('/sessions/{session}/preselection/questions', [PreselectionController::class, 'questions']);
    Route::post('/preselection/submit', [PreselectionController::class, 'submit']);

    // Routes protégées par token d'accès joueur
    Route::middleware('player.token')->group(function () {
        Route::post('/join', [PlayerGameController::class, 'join']);
        Route::get('/status', [PlayerGameController::class, 'status']);
        Route::post('/answer', [PlayerGameController::class, 'submitAnswer']);
        Route::post('/hint', [PlayerGameController::class, 'useHint']);
        Route::post('/pass-manche', [PlayerGameController::class, 'passManche']);
        Route::post('/finale-choice', [PlayerGameController::class, 'submitFinaleChoice']);
        Route::post('/broadcasting/auth', [PlayerGameController::class, 'broadcastingAuth']);
    });
});

/*
|--------------------------------------------------------------------------
| Routes Projection (écran public)
|--------------------------------------------------------------------------
*/
Route::prefix('projection')->group(function () {
    Route::post('/authenticate', [ProjectionController::class, 'authenticate']);
    Route::get('/{accessCode}/sync', [ProjectionController::class, 'sync']);
});
