<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SessionPlayerStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\QuestionResource;
use App\Http\Resources\SessionRoundResource;
use App\Models\PlayerAnswer;
use App\Models\Session;
use App\Models\SessionPlayer;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class DashboardController extends Controller
{
    /**
     * Tableau de bord en direct pour l'administrateur.
     */
    #[OA\Get(
        path: '/admin/sessions/{session}/dashboard',
        summary: 'Dashboard temps réel',
        description: 'Retourne les statistiques en direct : joueurs actifs/éliminés, stats question courante, cagnotte.',
        security: [['sanctum' => []]],
        tags: ['Dashboard'],
        parameters: [
            new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Données du dashboard', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'session_status', type: 'string'),
                    new OA\Property(property: 'jackpot', type: 'integer'),
                    new OA\Property(property: 'players_remaining', type: 'integer'),
                    new OA\Property(property: 'players_active', type: 'integer'),
                    new OA\Property(property: 'players_eliminated', type: 'integer'),
                    new OA\Property(property: 'current_round', type: 'object', nullable: true),
                    new OA\Property(property: 'current_question', type: 'object', nullable: true),
                ],
            )),
        ],
    )]
    public function show(Session $session): JsonResponse
    {
        $session->load(['currentRound', 'currentQuestion.choices', 'currentQuestion.hint']);

        $activePlayers = SessionPlayer::query()
            ->where('session_id', $session->id)
            ->where('status', SessionPlayerStatus::Active)
            ->with('player:id,pseudo')
            ->get();

        $activePlayersCount = $activePlayers->count();

        $activePlayersList = $activePlayers->map(fn ($sp) => [
            'id' => $sp->id,
            'pseudo' => $sp->player->pseudo ?? 'Inconnu',
        ])->values()->toArray();

        $eliminatedPlayers = SessionPlayer::query()
            ->where('session_id', $session->id)
            ->where('status', SessionPlayerStatus::Eliminated)
            ->with('player:id,full_name,pseudo')
            ->orderByDesc('eliminated_at')
            ->get()
            ->map(fn ($sp) => [
                'id' => $sp->id,
                'pseudo' => $sp->player->pseudo ?? 'Inconnu',
                'eliminated_at' => $sp->eliminated_at?->toIso8601String(),
                'elimination_reason' => $sp->elimination_reason,
                'capital_transferred' => $sp->capital,
            ]);

        $currentQuestionStats = null;

        if ($session->currentQuestion && $session->currentQuestion->status->value !== 'pending') {
            $answers = PlayerAnswer::query()
                ->where('question_id', $session->currentQuestion->id)
                ->where('is_second_chance', false)
                ->get();

            $currentQuestionStats = [
                'answers_received' => $answers->count(),
                'correct_count' => $answers->where('is_correct', true)->count(),
                'timeout_count' => $answers->where('is_timeout', true)->count(),
                'average_response_ms' => $answers->where('is_timeout', false)->avg('response_time_ms'),
            ];
        }

        return response()->json([
            'session' => [
                'id' => $session->id,
                'name' => $session->name,
                'status' => $session->status,
                'jackpot' => $session->jackpot,
                'players_remaining' => $session->players_remaining,
            ],
            'current_round' => $session->currentRound
                ? new SessionRoundResource($session->currentRound)
                : null,
            'current_question' => $session->currentQuestion
                ? new QuestionResource($session->currentQuestion)
                : null,
            'stats' => [
                'active_players' => $activePlayersCount,
                'active_players_list' => $activePlayersList,
                'eliminated_players' => $eliminatedPlayers,
                'current_question' => $currentQuestionStats,
            ],
        ]);
    }
}
