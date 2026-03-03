<?php

namespace App\Http\Controllers\Player;

use App\Enums\EliminationReason;
use App\Enums\QuestionStatus;
use App\Enums\RoundType;
use App\Enums\SessionPlayerStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Player\SubmitAnswerRequest;
use App\Http\Resources\QuestionResource;
use App\Http\Resources\SessionPlayerResource;
use App\Models\Elimination;
use App\Models\HintUsage;
use App\Models\PlayerAnswer;
use App\Models\RoundSkip;
use App\Models\Session;
use App\Models\SessionPlayer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GameController extends Controller
{
    /**
     * Rejoindre la salle de jeu avec un access_token.
     */
    public function join(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'access_token' => ['required', 'string'],
        ]);

        $sessionPlayer = SessionPlayer::query()
            ->where('access_token', $validated['access_token'])
            ->with(['session', 'player'])
            ->first();

        if (! $sessionPlayer) {
            return response()->json(['message' => 'Token d\'accès invalide.'], 401);
        }

        $sessionPlayer->update([
            'is_connected' => true,
            'last_connected_at' => now(),
        ]);

        return response()->json([
            'session_player' => new SessionPlayerResource($sessionPlayer->load('player')),
            'session' => [
                'id' => $sessionPlayer->session->id,
                'name' => $sessionPlayer->session->name,
                'status' => $sessionPlayer->session->status,
                'jackpot' => $sessionPlayer->session->jackpot,
                'players_remaining' => $sessionPlayer->session->players_remaining,
            ],
        ]);
    }

    /**
     * Récupère l'état actuel de la session pour le joueur.
     */
    public function status(Request $request): JsonResponse
    {
        $sessionPlayer = $this->resolveSessionPlayer($request);

        if (! $sessionPlayer) {
            return response()->json(['message' => 'Token invalide.'], 401);
        }

        $session = $sessionPlayer->session;
        $session->load(['currentRound', 'currentQuestion.choices']);

        $currentQuestion = null;

        if ($session->currentQuestion && $session->currentQuestion->status === QuestionStatus::Launched) {
            $currentQuestion = new QuestionResource($session->currentQuestion);
        }

        $existingAnswer = null;

        if ($session->currentQuestion) {
            $existingAnswer = PlayerAnswer::query()
                ->where('session_player_id', $sessionPlayer->id)
                ->where('question_id', $session->currentQuestion->id)
                ->where('is_second_chance', false)
                ->first();
        }

        return response()->json([
            'my_status' => [
                'status' => $sessionPlayer->status,
                'capital' => $sessionPlayer->capital,
                'hint_used' => (bool) HintUsage::query()
                    ->where('session_player_id', $sessionPlayer->id)
                    ->where('session_round_id', $session->current_round_id)
                    ->exists(),
            ],
            'session' => [
                'status' => $session->status,
                'jackpot' => $session->jackpot,
                'players_remaining' => $session->players_remaining,
            ],
            'current_round' => $session->currentRound ? [
                'id' => $session->currentRound->id,
                'round_number' => $session->currentRound->round_number,
                'name' => $session->currentRound->name,
                'round_type' => $session->currentRound->round_type,
            ] : null,
            'current_question' => $currentQuestion,
            'already_answered' => $existingAnswer !== null,
        ]);
    }

    /**
     * Soumet la réponse du joueur pour la question courante.
     */
    public function submitAnswer(SubmitAnswerRequest $request): JsonResponse
    {
        $sessionPlayer = $this->resolveSessionPlayer($request);

        if (! $sessionPlayer) {
            return response()->json(['message' => 'Token invalide.'], 401);
        }

        if ($sessionPlayer->status !== SessionPlayerStatus::Active) {
            return response()->json(['message' => 'Vous n\'participez plus à cette session.'], 422);
        }

        $session = $sessionPlayer->session;
        $question = $session->currentQuestion;

        if (! $question || $question->status !== QuestionStatus::Launched) {
            return response()->json(['message' => 'Aucune question active.'], 422);
        }

        if ($question->id !== $request->question_id) {
            return response()->json(['message' => 'Cette question n\'est plus active.'], 422);
        }

        // Vérifier si déjà répondu
        $alreadyAnswered = PlayerAnswer::query()
            ->where('session_player_id', $sessionPlayer->id)
            ->where('question_id', $question->id)
            ->where('is_second_chance', $request->boolean('is_second_chance', false))
            ->exists();

        if ($alreadyAnswered) {
            return response()->json(['message' => 'Vous avez déjà répondu à cette question.'], 422);
        }

        $hintUsed = HintUsage::query()
            ->where('session_player_id', $sessionPlayer->id)
            ->where('session_round_id', $session->current_round_id)
            ->exists();

        PlayerAnswer::create([
            'session_player_id' => $sessionPlayer->id,
            'question_id' => $question->id,
            'is_second_chance' => $request->boolean('is_second_chance', false),
            'second_chance_question_id' => $request->second_chance_question_id,
            'answer_value' => $request->answer_value,
            'selected_choice_id' => $request->selected_choice_id,
            'selected_sc_choice_id' => $request->selected_sc_choice_id,
            'hint_used' => $hintUsed,
            'response_time_ms' => $request->response_time_ms,
            'submitted_at' => now(),
            'is_timeout' => false,
        ]);

        return response()->json(['message' => 'Réponse soumise avec succès.']);
    }

    /**
     * Utilise l'indice pour la question courante (manche 2 uniquement).
     */
    public function useHint(Request $request): JsonResponse
    {
        $sessionPlayer = $this->resolveSessionPlayer($request);

        if (! $sessionPlayer) {
            return response()->json(['message' => 'Token invalide.'], 401);
        }

        $session = $sessionPlayer->session;
        $round = $session->currentRound;

        if (! $round || $round->round_type !== RoundType::Hint) {
            return response()->json(['message' => 'L\'indice n\'est disponible qu\'en manche 2.'], 422);
        }

        $alreadyUsed = HintUsage::query()
            ->where('session_player_id', $sessionPlayer->id)
            ->where('session_round_id', $round->id)
            ->exists();

        if ($alreadyUsed) {
            return response()->json(['message' => 'Vous avez déjà utilisé votre indice pour cette manche.'], 422);
        }

        $question = $session->currentQuestion;

        if (! $question || $question->status !== QuestionStatus::Launched) {
            return response()->json(['message' => 'Aucune question active.'], 422);
        }

        $hint = $question->hint;

        if (! $hint) {
            return response()->json(['message' => 'Aucun indice configuré pour cette question.'], 422);
        }

        HintUsage::create([
            'session_player_id' => $sessionPlayer->id,
            'session_round_id' => $round->id,
            'question_id' => $question->id,
            'used_at' => now(),
        ]);

        return response()->json([
            'message' => 'Indice activé.',
            'hint' => [
                'hint_type' => $hint->hint_type,
                'removed_choice_ids' => $hint->removed_choice_ids,
                'revealed_letters' => $hint->revealed_letters,
                'range_hint_text' => $hint->range_hint_text,
                'range_min' => $hint->range_min,
                'range_max' => $hint->range_max,
                'time_penalty_seconds' => $hint->time_penalty_seconds,
            ],
        ]);
    }

    /**
     * Passe la manche (manche 4 uniquement, coûte 1 000 de capital).
     */
    public function passManche(Request $request): JsonResponse
    {
        $sessionPlayer = $this->resolveSessionPlayer($request);

        if (! $sessionPlayer) {
            return response()->json(['message' => 'Token invalide.'], 401);
        }

        $session = $sessionPlayer->session;
        $round = $session->currentRound;

        if (! $round || $round->round_type !== RoundType::RoundSkip) {
            return response()->json(['message' => 'Le passage de manche n\'est possible qu\'en manche 4.'], 422);
        }

        $alreadySkipped = RoundSkip::query()
            ->where('session_player_id', $sessionPlayer->id)
            ->where('session_round_id', $round->id)
            ->exists();

        if ($alreadySkipped) {
            return response()->json(['message' => 'Vous avez déjà passé cette manche.'], 422);
        }

        DB::transaction(function () use ($sessionPlayer, $round, $session) {
            RoundSkip::create([
                'session_player_id' => $sessionPlayer->id,
                'session_round_id' => $round->id,
                'capital_lost' => 1000,
                'skipped_at' => now(),
            ]);

            $sessionPlayer->decrement('capital', 1000);

            Elimination::create([
                'session_player_id' => $sessionPlayer->id,
                'session_round_id' => $round->id,
                'reason' => EliminationReason::RoundSkip,
                'capital_transferred' => 1000,
                'eliminated_at' => now(),
            ]);

            $session->increment('jackpot', 1000);
        });

        return response()->json(['message' => 'Manche passée. 1 000 transférés à la cagnotte.']);
    }

    /**
     * Récupère le SessionPlayer via le header X-Player-Token.
     */
    private function resolveSessionPlayer(Request $request): ?SessionPlayer
    {
        $token = $request->header('X-Player-Token') ?? $request->input('access_token');

        if (! $token) {
            return null;
        }

        return SessionPlayer::query()
            ->where('access_token', $token)
            ->with('session.currentQuestion.choices', 'session.currentRound')
            ->first();
    }
}
