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

class DashboardController extends Controller
{
    /**
     * Tableau de bord en direct pour l'administrateur.
     */
    public function show(Session $session): JsonResponse
    {
        $session->load(['currentRound', 'currentQuestion.choices', 'currentQuestion.hint']);

        $activePlayers = SessionPlayer::query()
            ->where('session_id', $session->id)
            ->where('status', SessionPlayerStatus::Active)
            ->count();

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
                'active_players' => $activePlayers,
                'eliminated_players' => $eliminatedPlayers,
                'current_question' => $currentQuestionStats,
            ],
        ]);
    }
}
