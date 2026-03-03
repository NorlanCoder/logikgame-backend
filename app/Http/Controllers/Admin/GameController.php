<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AnswerType;
use App\Enums\EliminationReason;
use App\Enums\QuestionStatus;
use App\Enums\RegistrationStatus;
use App\Enums\RoundStatus;
use App\Enums\SessionPlayerStatus;
use App\Enums\SessionStatus;
use App\Http\Controllers\Controller;
use App\Models\Elimination;
use App\Models\PlayerAnswer;
use App\Models\Registration;
use App\Models\Session;
use App\Models\SessionPlayer;
use App\Models\SessionRound;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GameController extends Controller
{
    /**
     * Ouvre les inscriptions pour une session.
     */
    public function openRegistration(Session $session): JsonResponse
    {
        if ($session->status !== SessionStatus::Draft) {
            return $this->statusError('La session doit être en brouillon pour ouvrir les inscriptions.');
        }

        $session->update(['status' => SessionStatus::RegistrationOpen]);

        return response()->json(['status' => $session->status, 'message' => 'Inscriptions ouvertes.']);
    }

    /**
     * Clôture les inscriptions.
     */
    public function closeRegistration(Session $session): JsonResponse
    {
        if ($session->status !== SessionStatus::RegistrationOpen) {
            return $this->statusError('Les inscriptions ne sont pas ouvertes.');
        }

        $session->update(['status' => SessionStatus::RegistrationClosed]);

        return response()->json(['status' => $session->status, 'message' => 'Inscriptions clôturées.']);
    }

    /**
     * Lance la phase de pré-sélection.
     */
    public function openPreselection(Session $session): JsonResponse
    {
        if ($session->status !== SessionStatus::RegistrationClosed) {
            return $this->statusError('Les inscriptions doivent être clôturées avant la pré-sélection.');
        }

        $session->update(['status' => SessionStatus::Preselection]);

        // Mettre à jour le statut des inscriptions validées
        Registration::query()
            ->where('session_id', $session->id)
            ->where('status', RegistrationStatus::Registered)
            ->update(['status' => RegistrationStatus::PreselectionPending]);

        return response()->json(['status' => $session->status, 'message' => 'Pré-sélection ouverte.']);
    }

    /**
     * Sélectionne les joueurs (top N par score/vitesse) et crée les SessionPlayers.
     * L'admin passe en option une liste de registration_ids à sélectionner.
     */
    public function selectPlayers(Request $request, Session $session): JsonResponse
    {
        if (! in_array($session->status, [SessionStatus::Preselection, SessionStatus::RegistrationClosed])) {
            return $this->statusError('La pré-sélection doit être en cours ou les inscriptions clôturées.');
        }

        $validated = $request->validate([
            'registration_ids' => ['required', 'array', 'min:1'],
            'registration_ids.*' => ['integer', 'exists:registrations,id'],
        ]);

        DB::transaction(function () use ($validated, $session) {
            // Réinitialiser les sélections précédentes
            Registration::query()
                ->where('session_id', $session->id)
                ->where('status', RegistrationStatus::Selected)
                ->update(['status' => RegistrationStatus::Rejected]);

            SessionPlayer::query()
                ->where('session_id', $session->id)
                ->delete();

            $registrations = Registration::query()
                ->whereIn('id', $validated['registration_ids'])
                ->where('session_id', $session->id)
                ->with('player')
                ->get();

            foreach ($registrations as $registration) {
                $registration->update(['status' => RegistrationStatus::Selected]);

                SessionPlayer::create([
                    'session_id' => $session->id,
                    'player_id' => $registration->player_id,
                    'registration_id' => $registration->id,
                    'access_token' => Str::random(64),
                    'status' => SessionPlayerStatus::Waiting,
                    'capital' => 1000,
                ]);
            }

            $session->update([
                'status' => SessionStatus::Ready,
                'players_remaining' => $registrations->count(),
            ]);
        });

        return response()->json([
            'message' => count($validated['registration_ids']).' joueurs sélectionnés.',
            'players_count' => count($validated['registration_ids']),
        ]);
    }

    /**
     * Lance la session (démarre la première manche active).
     */
    public function startSession(Session $session): JsonResponse
    {
        if ($session->status !== SessionStatus::Ready) {
            return $this->statusError('La session doit être en statut \'prête\' pour démarrer.');
        }

        $firstRound = $session->rounds()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->first();

        if (! $firstRound) {
            return $this->statusError('Aucune manche active configurée.');
        }

        DB::transaction(function () use ($session, $firstRound) {
            $firstRound->update([
                'status' => RoundStatus::InProgress,
                'started_at' => now(),
            ]);

            $session->update([
                'status' => SessionStatus::InProgress,
                'current_round_id' => $firstRound->id,
                'started_at' => now(),
            ]);

            // Activer tous les joueurs sélectionnés
            SessionPlayer::query()
                ->where('session_id', $session->id)
                ->where('status', SessionPlayerStatus::Waiting)
                ->update(['status' => SessionPlayerStatus::Active]);
        });

        return response()->json([
            'message' => 'Session démarrée.',
            'current_round' => [
                'id' => $firstRound->id,
                'round_number' => $firstRound->round_number,
                'name' => $firstRound->name,
            ],
        ]);
    }

    /**
     * Lance une question spécifique (l'admin décide quand la question est visible).
     */
    public function launchQuestion(Request $request, Session $session): JsonResponse
    {
        if ($session->status !== SessionStatus::InProgress) {
            return $this->statusError('La session n\'est pas en cours.');
        }

        $validated = $request->validate([
            'question_id' => ['required', 'integer', 'exists:questions,id'],
        ]);

        $question = \App\Models\Question::findOrFail($validated['question_id']);

        if ($question->status !== QuestionStatus::Pending) {
            return $this->statusError('Cette question a déjà été lancée.');
        }

        if ($question->sessionRound->session_id !== $session->id) {
            return $this->statusError('Cette question n\'appartient pas à cette session.');
        }

        DB::transaction(function () use ($session, $question) {
            $question->update([
                'status' => QuestionStatus::Launched,
                'launched_at' => now(),
            ]);

            $session->update(['current_question_id' => $question->id]);
        });

        return response()->json([
            'message' => 'Question lancée.',
            'question_id' => $question->id,
            'launched_at' => $question->launched_at?->toIso8601String(),
            'duration' => $question->duration,
        ]);
    }

    /**
     * Clôture la question courante et calcule les éliminations.
     */
    public function closeQuestion(Session $session): JsonResponse
    {
        if ($session->status !== SessionStatus::InProgress) {
            return $this->statusError('La session n\'est pas en cours.');
        }

        $question = $session->currentQuestion;

        if (! $question || $question->status !== QuestionStatus::Launched) {
            return $this->statusError('Aucune question en cours.');
        }

        $round = $session->currentRound;
        $eliminated = [];

        DB::transaction(function () use ($session, $question, $round, &$eliminated) {
            $question->update([
                'status' => QuestionStatus::Closed,
                'closed_at' => now(),
            ]);

            // Marquer les réponses timeout (joueurs qui n'ont pas répondu)
            $activePlayers = SessionPlayer::query()
                ->where('session_id', $session->id)
                ->where('status', SessionPlayerStatus::Active)
                ->get();

            foreach ($activePlayers as $player) {
                $answer = PlayerAnswer::query()
                    ->where('session_player_id', $player->id)
                    ->where('question_id', $question->id)
                    ->where('is_second_chance', false)
                    ->first();

                if (! $answer) {
                    // Pas de réponse = timeout
                    PlayerAnswer::create([
                        'session_player_id' => $player->id,
                        'question_id' => $question->id,
                        'is_second_chance' => false,
                        'is_correct' => false,
                        'is_timeout' => true,
                        'submitted_at' => now(),
                    ]);
                } else {
                    // Vérifier si la réponse est correcte
                    $isCorrect = $this->checkAnswer($question, $answer);
                    $answer->update(['is_correct' => $isCorrect]);
                }
            }

            // Appliquer les éliminations selon le type de manche
            $eliminated = $this->applyEliminations($session, $question, $round);
        });

        return response()->json([
            'message' => 'Question clôturée.',
            'eliminated_count' => count($eliminated),
            'eliminated_player_ids' => $eliminated,
        ]);
    }

    /**
     * Révèle la bonne réponse.
     */
    public function revealAnswer(Session $session): JsonResponse
    {
        $question = $session->currentQuestion;

        if (! $question || $question->status !== QuestionStatus::Closed) {
            return $this->statusError('Aucune question clôturée à révéler.');
        }

        $question->update([
            'status' => QuestionStatus::Revealed,
            'revealed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Réponse révélée.',
            'correct_answer' => $question->correct_answer,
        ]);
    }

    /**
     * Passe à la manche suivante active.
     */
    public function nextRound(Session $session): JsonResponse
    {
        if ($session->status !== SessionStatus::InProgress) {
            return $this->statusError('La session n\'est pas en cours.');
        }

        $currentRound = $session->currentRound;

        if ($currentRound) {
            $currentRound->update([
                'status' => RoundStatus::Completed,
                'ended_at' => now(),
            ]);
        }

        $nextRound = $session->rounds()
            ->where('is_active', true)
            ->where('display_order', '>', $currentRound?->display_order ?? 0)
            ->orderBy('display_order')
            ->first();

        if (! $nextRound) {
            // Plus de manches — fin de session
            $session->update([
                'status' => SessionStatus::Ended,
                'current_round_id' => null,
                'current_question_id' => null,
                'ended_at' => now(),
            ]);

            return response()->json(['message' => 'Session terminée. Plus de manches actives.', 'session_ended' => true]);
        }

        $nextRound->update([
            'status' => RoundStatus::InProgress,
            'started_at' => now(),
        ]);

        $session->update([
            'current_round_id' => $nextRound->id,
            'current_question_id' => null,
        ]);

        return response()->json([
            'message' => 'Passage à la manche '.$nextRound->round_number.'.',
            'current_round' => [
                'id' => $nextRound->id,
                'round_number' => $nextRound->round_number,
                'name' => $nextRound->name,
                'round_type' => $nextRound->round_type,
            ],
        ]);
    }

    /**
     * Termine la session manuellement.
     */
    public function endSession(Session $session): JsonResponse
    {
        if (! in_array($session->status, [SessionStatus::InProgress, SessionStatus::Paused])) {
            return $this->statusError('La session n\'est pas en cours.');
        }

        $session->update([
            'status' => SessionStatus::Ended,
            'ended_at' => now(),
        ]);

        return response()->json(['message' => 'Session terminée.']);
    }

    /**
     * Vérifie si la réponse d'un joueur est correcte.
     */
    private function checkAnswer(\App\Models\Question $question, PlayerAnswer $answer): bool
    {
        return match ($question->answer_type) {
            AnswerType::Qcm => $answer->selected_choice_id !== null
                && $question->choices()->where('id', $answer->selected_choice_id)->where('is_correct', true)->exists(),

            AnswerType::Text => $answer->answer_value !== null
                && strtolower(trim($this->removeAccents($answer->answer_value)))
                === strtolower(trim($this->removeAccents($question->correct_answer))),

            AnswerType::Number => $answer->answer_value !== null
                && is_numeric($answer->answer_value)
                && abs((float) $answer->answer_value - (float) $question->correct_answer) <= 0.0001,

            default => false,
        };
    }

    /**
     * Applique les éliminations selon le type de manche.
     *
     * @return list<int> IDs des session_players éliminés
     */
    private function applyEliminations(Session $session, \App\Models\Question $question, SessionRound $round): array
    {
        // Manches 1, 2, 3, 4 : élimination directe si mauvaise réponse ou timeout
        $directEliminationRounds = [
            \App\Enums\RoundType::SuddenDeath,
            \App\Enums\RoundType::Hint,
            \App\Enums\RoundType::RoundSkip,
        ];

        if (! in_array($round->round_type, $directEliminationRounds)) {
            return [];
        }

        $wrongAnswerPlayers = PlayerAnswer::query()
            ->where('question_id', $question->id)
            ->where('is_correct', false)
            ->where('is_second_chance', false)
            ->pluck('session_player_id')
            ->toArray();

        if (empty($wrongAnswerPlayers)) {
            return [];
        }

        $eliminated = [];

        foreach ($wrongAnswerPlayers as $sessionPlayerId) {
            $sessionPlayer = SessionPlayer::find($sessionPlayerId);

            if (! $sessionPlayer || $sessionPlayer->status !== SessionPlayerStatus::Active) {
                continue;
            }

            $reason = PlayerAnswer::query()
                ->where('session_player_id', $sessionPlayerId)
                ->where('question_id', $question->id)
                ->value('is_timeout')
                ? EliminationReason::Timeout
                : EliminationReason::WrongAnswer;

            $capitalTransferred = $sessionPlayer->capital;

            $sessionPlayer->update([
                'status' => SessionPlayerStatus::Eliminated,
                'eliminated_at' => now(),
                'elimination_reason' => $reason,
                'eliminated_in_round_id' => $round->id,
            ]);

            Elimination::create([
                'session_player_id' => $sessionPlayer->id,
                'session_round_id' => $round->id,
                'question_id' => $question->id,
                'reason' => $reason,
                'capital_transferred' => $capitalTransferred,
                'eliminated_at' => now(),
            ]);

            // Ajouter le capital à la cagnotte
            $session->increment('jackpot', $capitalTransferred);
            $session->decrement('players_remaining');

            $eliminated[] = $sessionPlayerId;
        }

        return $eliminated;
    }

    /**
     * Supprime les accents pour la comparaison de texte libre.
     */
    private function removeAccents(string $text): string
    {
        return iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    }

    private function statusError(string $message): JsonResponse
    {
        return response()->json(['message' => $message], 422);
    }
}
