<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AnswerType;
use App\Enums\EliminationReason;
use App\Enums\FinaleChoiceType;
use App\Enums\FinaleScenario;
use App\Enums\JackpotTransactionType;
use App\Enums\QuestionStatus;
use App\Enums\RegistrationStatus;
use App\Enums\RoundStatus;
use App\Enums\RoundType;
use App\Enums\SessionPlayerStatus;
use App\Enums\SessionStatus;
use App\Events\AnswerResult;
use App\Events\AnswerRevealed;
use App\Events\DuelQuestionsAssigned;
use App\Events\FinaleChoicesRevealed;
use App\Events\FinaleVoteLaunched;
use App\Events\GameEnded;
use App\Events\JackpotUpdated;
use App\Events\PlayerEliminated;
use App\Events\QuestionClosed;
use App\Events\QuestionLaunched;
use App\Events\RoundEnded;
use App\Events\RoundStarted;
use App\Events\SecondChanceClosed;
use App\Events\SecondChanceLaunched;
use App\Events\SecondChanceRevealed;
use App\Events\Top4Finalized;
use App\Http\Controllers\Controller;
use App\Models\Elimination;
use App\Models\FinaleChoice;
use App\Models\FinalResult;
use App\Models\JackpotTransaction;
use App\Models\PlayerAnswer;
use App\Models\Question;
use App\Models\Registration;
use App\Models\Round6PlayerJackpot;
use App\Models\Round6TurnOrder;
use App\Models\RoundRanking;
use App\Models\SecondChanceQuestion;
use App\Models\Session;
use App\Models\SessionPlayer;
use App\Models\SessionRound;
use App\Notifications\PlayerRejected;
use App\Notifications\PlayerSelected;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class GameController extends Controller
{
    // ───────────────────────────────────────────────────────────
    //  Phase pré-jeu : inscriptions, pré-sélection, démarrage
    // ───────────────────────────────────────────────────────────

    /**
     * Ouvre les inscriptions pour une session.
     */
    #[OA\Post(
        path: '/admin/sessions/{session}/game/open-registration',
        summary: 'Ouvrir la pré-sélection (inscriptions + quiz)',
        security: [['sanctum' => []]],
        tags: ['Game Engine'],
        parameters: [new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Pré-sélection ouverte — inscriptions et quiz actifs'),
            new OA\Response(response: 422, description: 'Statut invalide'),
        ],
    )]
    public function openRegistration(Session $session): JsonResponse
    {
        if ($session->status !== SessionStatus::Draft) {
            return $this->statusError('La session doit être en brouillon pour ouvrir la pré-sélection.');
        }

        $session->update(['status' => SessionStatus::Preselection]);

        return response()->json(['status' => $session->status, 'message' => 'Pré-sélection ouverte — inscriptions et quiz actifs.']);
    }

    /**
     * Clôture les inscriptions.
     */
    #[OA\Post(
        path: '/admin/sessions/{session}/game/close-registration',
        summary: 'Clôturer la pré-sélection (inscriptions + quiz)',
        security: [['sanctum' => []]],
        tags: ['Game Engine'],
        parameters: [new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Pré-sélection clôturée'),
            new OA\Response(response: 422, description: 'Statut invalide'),
        ],
    )]
    public function closeRegistration(Session $session): JsonResponse
    {
        if (! in_array($session->status, [SessionStatus::Preselection, SessionStatus::RegistrationOpen])) {
            return $this->statusError('La pré-sélection n\'est pas active.');
        }

        $session->update(['status' => SessionStatus::RegistrationClosed]);

        return response()->json(['status' => $session->status, 'message' => 'Pré-sélection clôturée.']);
    }

    /**
     * Lance la phase de pré-sélection.
     */
    #[OA\Post(
        path: '/admin/sessions/{session}/game/open-preselection',
        summary: 'Ouvrir la phase de pré-sélection',
        security: [['sanctum' => []]],
        tags: ['Game Engine'],
        parameters: [new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Pré-sélection ouverte'),
            new OA\Response(response: 422, description: 'Statut invalide'),
        ],
    )]
    public function openPreselection(Session $session): JsonResponse
    {
        if (! in_array($session->status, [SessionStatus::RegistrationClosed, SessionStatus::RegistrationOpen, SessionStatus::Draft])) {
            return $this->statusError('Statut invalide pour ouvrir la pré-sélection.');
        }

        $session->update(['status' => SessionStatus::Preselection]);

        Registration::query()
            ->where('session_id', $session->id)
            ->where('status', RegistrationStatus::Registered)
            ->update(['status' => RegistrationStatus::PreselectionPending]);

        return response()->json(['status' => $session->status, 'message' => 'Pré-sélection ouverte.']);
    }

    /**
     * Sélectionne les joueurs et crée les SessionPlayers.
     */
    #[OA\Post(
        path: '/admin/sessions/{session}/game/select-players',
        summary: 'Sélectionner les joueurs pour la session',
        security: [['sanctum' => []]],
        tags: ['Game Engine'],
        parameters: [new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['registration_ids'],
                properties: [
                    new OA\Property(property: 'registration_ids', type: 'array', items: new OA\Items(type: 'integer')),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Joueurs sélectionnés'),
            new OA\Response(response: 422, description: 'Statut invalide ou validation échouée'),
        ],
    )]
    public function selectPlayers(Request $request, Session $session): JsonResponse
    {
        if (! in_array($session->status, [SessionStatus::Preselection, SessionStatus::RegistrationClosed, SessionStatus::Ready])) {
            return $this->statusError('La pré-sélection doit être en cours, les inscriptions clôturées, ou la session prête.');
        }

        $validated = $request->validate([
            'registration_ids' => ['required', 'array', 'min:1'],
            'registration_ids.*' => ['integer', 'exists:registrations,id'],
        ]);

        DB::transaction(function () use ($validated, $session) {
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
                'players_remaining' => $registrations->count(),
            ]);
        });

        return response()->json([
            'message' => count($validated['registration_ids']).' joueurs sélectionnés.',
            'players_count' => count($validated['registration_ids']),
        ]);
    }

    /**
     * Confirme la sélection, passe en Ready et envoie les notifications.
     */
    #[OA\Post(
        path: '/admin/sessions/{session}/game/confirm-selection',
        summary: 'Confirmer la sélection et notifier les joueurs',
        description: 'Passe la session en statut Ready et envoie les emails aux joueurs sélectionnés et rejetés.',
        security: [['sanctum' => []]],
        tags: ['Game Engine'],
        parameters: [new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Sélection confirmée, notifications envoyées'),
            new OA\Response(response: 422, description: 'Statut invalide ou aucun joueur sélectionné'),
        ],
    )]
    public function confirmSelection(Session $session): JsonResponse
    {
        if (! in_array($session->status, [SessionStatus::Preselection, SessionStatus::RegistrationClosed, SessionStatus::Ready])) {
            return $this->statusError('La session doit être en pré-sélection, inscriptions clôturées, ou prête.');
        }

        $selectedPlayers = SessionPlayer::query()
            ->where('session_id', $session->id)
            ->with('player')
            ->get();

        if ($selectedPlayers->isEmpty()) {
            return $this->statusError('Aucun joueur sélectionné. Utilisez d\'abord select-players.');
        }

        $session->update([
            'status' => SessionStatus::Ready,
        ]);

        foreach ($selectedPlayers as $sessionPlayer) {
            $sessionPlayer->player->notify(new PlayerSelected($session, $sessionPlayer));
        }

        $rejectedRegistrations = Registration::query()
            ->where('session_id', $session->id)
            ->where('status', RegistrationStatus::Rejected)
            ->with('player')
            ->get();

        foreach ($rejectedRegistrations as $registration) {
            $registration->player->notify(new PlayerRejected($session));
        }

        return response()->json([
            'message' => 'Sélection confirmée. '.$selectedPlayers->count().' joueurs notifiés.',
            'players_count' => $selectedPlayers->count(),
        ]);
    }

    /**
     * Lance la session (démarre la première manche active).
     */
    #[OA\Post(
        path: '/admin/sessions/{session}/game/start',
        summary: 'Démarrer la session de jeu',
        security: [['sanctum' => []]],
        tags: ['Game Engine'],
        parameters: [new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Session démarrée'),
            new OA\Response(response: 422, description: 'Statut invalide'),
        ],
    )]
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

            SessionPlayer::query()
                ->where('session_id', $session->id)
                ->where('status', SessionPlayerStatus::Waiting)
                ->update(['status' => SessionPlayerStatus::Active]);
        });

        event(new RoundStarted(
            $session,
            $firstRound->round_number,
            $firstRound->name,
            $firstRound->round_type->value,
            $firstRound->rules_description,
        ));

        return response()->json([
            'message' => 'Session démarrée.',
            'current_round' => [
                'id' => $firstRound->id,
                'round_number' => $firstRound->round_number,
                'name' => $firstRound->name,
            ],
        ]);
    }

    // ───────────────────────────────────────────────────────────
    //  Cycle de question principal
    // ───────────────────────────────────────────────────────────

    /**
     * Lance une question (l'admin décide quand la question est visible).
     */
    #[OA\Post(
        path: '/admin/sessions/{session}/game/launch-question',
        summary: 'Lancer une question',
        security: [['sanctum' => []]],
        tags: ['Game Engine'],
        parameters: [new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['question_id'],
                properties: [
                    new OA\Property(property: 'question_id', type: 'integer'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Question lancée'),
            new OA\Response(response: 422, description: 'Statut invalide'),
        ],
    )]
    public function launchQuestion(Request $request, Session $session): JsonResponse
    {
        if ($session->status !== SessionStatus::InProgress) {
            return $this->statusError('La session n\'est pas en cours.');
        }

        $validated = $request->validate([
            'question_id' => ['required', 'integer', 'exists:questions,id'],
            'assigned_player_id' => ['nullable', 'integer', 'exists:session_players,id'],
        ]);

        $question = Question::findOrFail($validated['question_id']);

        if ($question->status !== QuestionStatus::Pending) {
            return $this->statusError('Cette question a déjà été lancée.');
        }

        if ($question->sessionRound->session_id !== $session->id) {
            return $this->statusError('Cette question n\'appartient pas à cette session.');
        }

        // En duels, le joueur assigné est requis
        $round = $question->sessionRound;
        $assignedPlayerId = $validated['assigned_player_id'] ?? $question->assigned_player_id;

        if (in_array($round->round_type, [RoundType::DuelJackpot, RoundType::DuelElimination]) && ! $assignedPlayerId) {
            return $this->statusError('En manche Duel, un joueur assigné est requis (assigned_player_id).');
        }

        DB::transaction(function () use ($session, $question, $assignedPlayerId) {
            $question->update([
                'status' => QuestionStatus::Launched,
                'launched_at' => now(),
                'assigned_player_id' => $assignedPlayerId,
            ]);

            $session->update(['current_question_id' => $question->id]);
        });

        event(new QuestionLaunched($session, $question));

        return response()->json([
            'message' => 'Question lancée.',
            'question_id' => $question->id,
            'launched_at' => $question->launched_at?->toIso8601String(),
            'duration' => $question->duration,
        ]);
    }

    /**
     * Clôture la question courante et évalue les réponses.
     * Pour SecondChance (manche 3), les joueurs en échec ne sont PAS éliminés ici.
     */
    #[OA\Post(
        path: '/admin/sessions/{session}/game/close-question',
        summary: 'Clôturer la question courante et évaluer les réponses',
        security: [['sanctum' => []]],
        tags: ['Game Engine'],
        parameters: [new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Question clôturée avec résultats'),
            new OA\Response(response: 422, description: 'Statut invalide ou aucune question en cours'),
        ],
    )]
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
        $needsSecondChance = false;
        $failedPlayerIds = [];

        DB::transaction(function () use ($session, $question, $round, &$eliminated, &$needsSecondChance, &$failedPlayerIds) {
            $question->update([
                'status' => QuestionStatus::Closed,
                'closed_at' => now(),
            ]);

            // Déterminer quels joueurs doivent répondre (tous actifs sauf assigné à un autre joueur en duels)
            $activePlayers = $this->getRespondingPlayers($session, $question, $round);

            foreach ($activePlayers as $player) {
                $answer = PlayerAnswer::query()
                    ->where('session_player_id', $player->id)
                    ->where('question_id', $question->id)
                    ->where('is_second_chance', false)
                    ->first();

                if (! $answer) {
                    PlayerAnswer::create([
                        'session_player_id' => $player->id,
                        'question_id' => $question->id,
                        'is_second_chance' => false,
                        'is_correct' => false,
                        'is_timeout' => true,
                        'submitted_at' => now(),
                    ]);
                } else {
                    $isCorrect = $this->checkAnswer($question, $answer);
                    $answer->update(['is_correct' => $isCorrect]);
                }
            }

            // Envoyer le résultat individuel à chaque joueur
            foreach ($activePlayers as $player) {
                $answer = PlayerAnswer::query()
                    ->where('session_player_id', $player->id)
                    ->where('question_id', $question->id)
                    ->where('is_second_chance', false)
                    ->first();

                if ($answer) {
                    event(new AnswerResult(
                        $player,
                        $question->id,
                        (bool) $answer->is_correct,
                        $question->correct_answer,
                    ));
                }
            }

            // Résolution selon le type de manche
            $eliminated = match ($round->round_type) {
                RoundType::SecondChance => $this->handleSecondChanceEvaluation($session, $question, $round, $needsSecondChance, $failedPlayerIds),
                RoundType::DuelJackpot => $this->handleDuelJackpotClose($session, $question, $round),
                RoundType::DuelElimination => $this->handleDuelEliminationClose($session, $question, $round),
                default => $this->applyDirectEliminations($session, $question, $round),
            };
        });

        $response = [
            'message' => 'Question clôturée.',
            'eliminated_count' => count($eliminated),
            'eliminated_player_ids' => $eliminated,
        ];

        // Diffuser les événements temps réel
        if (count($eliminated) > 0) {
            $session->refresh();
            $eliminatedData = SessionPlayer::whereIn('id', $eliminated)
                ->with('player:id,pseudo')
                ->get()
                ->map(fn (SessionPlayer $sp) => [
                    'pseudo' => $sp->player->pseudo ?? 'Joueur',
                    'reason' => $sp->elimination_reason?->value ?? 'wrong_answer',
                ])->toArray();

            event(new PlayerEliminated($session, $eliminatedData, $session->players_remaining, $session->jackpot, $eliminated));
            event(new JackpotUpdated($session, $session->jackpot, $session->players_remaining));
        }

        // Construire les résultats par joueur pour la projection
        $playerResults = PlayerAnswer::query()
            ->where('question_id', $question->id)
            ->where('is_second_chance', false)
            ->with('sessionPlayer.player:id,pseudo')
            ->get()
            ->map(fn (PlayerAnswer $pa) => [
                'pseudo' => $pa->sessionPlayer?->player?->pseudo ?? 'Joueur',
                'is_correct' => (bool) $pa->is_correct,
                'is_timeout' => (bool) $pa->is_timeout,
            ])
            ->values()
            ->toArray();

        event(new QuestionClosed(
            $session,
            $question->id,
            PlayerAnswer::where('question_id', $question->id)->where('is_second_chance', false)->count(),
            PlayerAnswer::where('question_id', $question->id)->where('is_second_chance', false)->where('is_correct', true)->count(),
            count($eliminated),
            $needsSecondChance
                ? SessionPlayer::whereIn('id', $failedPlayerIds)
                    ->with('player:id,pseudo')
                    ->get()
                    ->map(fn (SessionPlayer $sp) => $sp->player->pseudo ?? 'Joueur')
                    ->values()
                    ->toArray()
                : [],
            $playerResults,
        ));

        if ($needsSecondChance) {
            $response['needs_second_chance'] = true;
            $response['failed_player_ids'] = $failedPlayerIds;
        }

        return response()->json($response);
    }

    // ───────────────────────────────────────────────────────────
    //  Manche 3 — Seconde Chance
    // ───────────────────────────────────────────────────────────

    /**
     * Lance la question de seconde chance associée à la question principale courante.
     */
    #[OA\Post(
        path: '/admin/sessions/{session}/game/launch-second-chance',
        summary: 'Lancer la question de seconde chance',
        security: [['sanctum' => []]],
        tags: ['Game Engine'],
        parameters: [new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Seconde chance lancée'),
            new OA\Response(response: 422, description: 'Manche non compatible ou pas de question'),
        ],
    )]
    public function launchSecondChance(Session $session): JsonResponse
    {
        if ($session->status !== SessionStatus::InProgress) {
            return $this->statusError('La session n\'est pas en cours.');
        }

        $round = $session->currentRound;

        if (! $round || $round->round_type !== RoundType::SecondChance) {
            return $this->statusError('Cette action n\'est disponible qu\'en manche Seconde Chance.');
        }

        $question = $session->currentQuestion;

        if (! $question) {
            return $this->statusError('Aucune question principale courante.');
        }

        $scQuestion = $question->secondChanceQuestion;

        if (! $scQuestion) {
            return $this->statusError('Aucune question de seconde chance configurée pour cette question.');
        }

        if ($scQuestion->status !== QuestionStatus::Pending) {
            return $this->statusError('La question de seconde chance a déjà été lancée.');
        }

        $scQuestion->update([
            'status' => QuestionStatus::Launched,
            'launched_at' => now(),
        ]);

        // Trouver les joueurs ayant échoué la question principale
        $failedPlayerIds = PlayerAnswer::query()
            ->where('question_id', $question->id)
            ->where('is_correct', false)
            ->where('is_second_chance', false)
            ->pluck('session_player_id')
            ->toArray();

        event(new SecondChanceLaunched($session, $scQuestion, $question->id, $failedPlayerIds));

        return response()->json([
            'message' => 'Question de seconde chance lancée.',
            'second_chance_question_id' => $scQuestion->id,
            'duration' => $scQuestion->duration,
        ]);
    }

    /**
     * Clôture la question de seconde chance et élimine ceux qui échouent.
     */
    #[OA\Post(
        path: '/admin/sessions/{session}/game/close-second-chance',
        summary: 'Clôturer la seconde chance et éliminer les perdants',
        security: [['sanctum' => []]],
        tags: ['Game Engine'],
        parameters: [new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Seconde chance clôturée'),
            new OA\Response(response: 422, description: 'Statut invalide'),
        ],
    )]
    public function closeSecondChance(Session $session): JsonResponse
    {
        if ($session->status !== SessionStatus::InProgress) {
            return $this->statusError('La session n\'est pas en cours.');
        }

        $round = $session->currentRound;
        $question = $session->currentQuestion;

        if (! $question) {
            return $this->statusError('Aucune question principale courante.');
        }

        $scQuestion = $question->secondChanceQuestion;

        if (! $scQuestion || $scQuestion->status !== QuestionStatus::Launched) {
            return $this->statusError('Aucune question de seconde chance en cours.');
        }

        $eliminated = [];

        DB::transaction(function () use ($session, $question, $scQuestion, $round, &$eliminated) {
            $scQuestion->update([
                'status' => QuestionStatus::Closed,
                'closed_at' => now(),
            ]);

            // Trouver les joueurs qui avaient échoué à la question principale
            $failedPlayerIds = PlayerAnswer::query()
                ->where('question_id', $question->id)
                ->where('is_correct', false)
                ->where('is_second_chance', false)
                ->pluck('session_player_id');

            foreach ($failedPlayerIds as $sessionPlayerId) {
                $sessionPlayer = SessionPlayer::find($sessionPlayerId);

                if (! $sessionPlayer || $sessionPlayer->status !== SessionPlayerStatus::Active) {
                    continue;
                }

                // Vérifier la réponse de seconde chance
                $scAnswer = PlayerAnswer::query()
                    ->where('session_player_id', $sessionPlayerId)
                    ->where('question_id', $question->id)
                    ->where('is_second_chance', true)
                    ->first();

                if (! $scAnswer) {
                    // Pas de réponse = timeout sur la seconde chance
                    PlayerAnswer::create([
                        'session_player_id' => $sessionPlayerId,
                        'question_id' => $question->id,
                        'is_second_chance' => true,
                        'second_chance_question_id' => $scQuestion->id,
                        'is_correct' => false,
                        'is_timeout' => true,
                        'submitted_at' => now(),
                    ]);
                    $scAnswer = PlayerAnswer::query()
                        ->where('session_player_id', $sessionPlayerId)
                        ->where('question_id', $question->id)
                        ->where('is_second_chance', true)
                        ->first();
                } else {
                    $isCorrect = $this->checkSecondChanceAnswer($scQuestion, $scAnswer);
                    $scAnswer->update(['is_correct' => $isCorrect]);
                }

                // Envoyer le résultat SC individuel au joueur
                event(new AnswerResult(
                    $sessionPlayer,
                    $question->id,
                    (bool) $scAnswer->is_correct,
                    $scQuestion->correct_answer,
                ));

                // Si échec à la seconde chance → élimination
                if (! $scAnswer->is_correct) {
                    $eliminated[] = $this->eliminatePlayer(
                        $session,
                        $sessionPlayer,
                        $round,
                        $question,
                        EliminationReason::SecondChanceFailed,
                    );
                }
            }
        });

        // Diffuser les événements temps réel
        if (count($eliminated) > 0) {
            $session->refresh();
            $eliminatedData = SessionPlayer::whereIn('id', $eliminated)
                ->with('player:id,pseudo')
                ->get()
                ->map(fn (SessionPlayer $sp) => [
                    'pseudo' => $sp->player->pseudo ?? 'Joueur',
                    'reason' => $sp->elimination_reason?->value ?? 'second_chance_failed',
                ])->toArray();

            event(new PlayerEliminated($session, $eliminatedData, $session->players_remaining, $session->jackpot, $eliminated));
            event(new JackpotUpdated($session, $session->jackpot, $session->players_remaining));
        }

        // Construire les résultats SC par joueur pour la projection
        $scPlayerResults = PlayerAnswer::query()
            ->where('question_id', $question->id)
            ->where('is_second_chance', true)
            ->with('sessionPlayer.player:id,pseudo')
            ->get()
            ->map(fn (PlayerAnswer $pa) => [
                'pseudo' => $pa->sessionPlayer?->player?->pseudo ?? 'Joueur',
                'is_correct' => (bool) $pa->is_correct,
                'is_timeout' => (bool) $pa->is_timeout,
            ])
            ->values()
            ->toArray();

        // Diffuser la clôture de la seconde chance (pour la projection)
        event(new SecondChanceClosed($session, $question->id, $scPlayerResults));

        return response()->json([
            'message' => 'Seconde chance clôturée.',
            'eliminated_count' => count($eliminated),
            'eliminated_player_ids' => $eliminated,
        ]);
    }

    /**
     * Révèle la bonne réponse de la question de seconde chance.
     */
    #[OA\Post(
        path: '/admin/sessions/{session}/game/reveal-second-chance',
        summary: 'Révéler la réponse de la seconde chance',
        security: [['sanctum' => []]],
        tags: ['Game Engine'],
        parameters: [new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Réponse SC révélée'),
            new OA\Response(response: 422, description: 'Statut invalide'),
        ],
    )]
    public function revealSecondChance(Session $session): JsonResponse
    {
        $question = $session->currentQuestion;

        if (! $question) {
            return $this->statusError('Aucune question principale courante.');
        }

        $scQuestion = $question->secondChanceQuestion;

        if (! $scQuestion || $scQuestion->status !== QuestionStatus::Closed) {
            return $this->statusError('Aucune question de seconde chance clôturée à révéler.');
        }

        $scQuestion->update([
            'status' => QuestionStatus::Revealed,
        ]);

        $choicesData = $scQuestion->choices->map(fn ($c) => [
            'id' => $c->id,
            'label' => $c->label,
            'is_correct' => $c->is_correct,
        ])->toArray();

        event(new SecondChanceRevealed(
            $session,
            $question->id,
            $scQuestion->correct_answer,
            $choicesData,
        ));

        return response()->json([
            'message' => 'Réponse de seconde chance révélée.',
            'correct_answer' => $scQuestion->correct_answer,
        ]);
    }

    // ───────────────────────────────────────────────────────────
    //  Manche 5 — Top 4 Elimination (classement en fin de manche)
    // ───────────────────────────────────────────────────────────

    /**
     * Finalise la manche 5 : classe les joueurs et ne garde que le top 4.
     */
    #[OA\Post(
        path: '/admin/sessions/{session}/game/finalize-top4',
        summary: 'Finaliser le top 4 (manche 5)',
        security: [['sanctum' => []]],
        tags: ['Game Engine'],
        parameters: [new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Top 4 finalisé avec classements'),
            new OA\Response(response: 422, description: 'Manche non compatible'),
        ],
    )]
    public function finalizeTop4(Session $session): JsonResponse
    {
        $round = $session->currentRound;

        if (! $round || $round->round_type !== RoundType::Top4Elimination) {
            return $this->statusError('Cette action n\'est disponible qu\'en manche Top 4.');
        }

        // Vérifier que toutes les questions sont clôturées ou révélées
        $pendingQuestions = $round->questions()
            ->whereNotIn('status', [QuestionStatus::Closed, QuestionStatus::Revealed])
            ->count();

        if ($pendingQuestions > 0) {
            return $this->statusError('Toutes les questions doivent être clôturées avant la finalisation.');
        }

        $eliminated = [];

        DB::transaction(function () use ($session, $round, &$eliminated) {
            $activePlayers = SessionPlayer::query()
                ->where('session_id', $session->id)
                ->where('status', SessionPlayerStatus::Active)
                ->get();

            // Calculer le classement pour chaque joueur
            $rankings = [];

            foreach ($activePlayers as $player) {
                $stats = PlayerAnswer::query()
                    ->where('session_player_id', $player->id)
                    ->whereHas('question', fn ($q) => $q->where('session_round_id', $round->id))
                    ->where('is_second_chance', false)
                    ->selectRaw('SUM(is_correct) as correct_count, SUM(CASE WHEN is_correct THEN response_time_ms ELSE 0 END) as total_time')
                    ->first();

                $rankings[] = [
                    'session_player_id' => $player->id,
                    'correct_count' => (int) ($stats->correct_count ?? 0),
                    'total_time' => (int) ($stats->total_time ?? 0),
                ];
            }

            // Trier : bonnes réponses DESC, puis temps ASC
            usort($rankings, function ($a, $b) {
                if ($a['correct_count'] !== $b['correct_count']) {
                    return $b['correct_count'] - $a['correct_count'];
                }

                return $a['total_time'] - $b['total_time'];
            });

            // Enregistrer les classements et sélectionner le top 4
            foreach ($rankings as $index => $ranking) {
                $rank = $index + 1;
                $isQualified = $rank <= 4;

                RoundRanking::updateOrCreate(
                    [
                        'session_round_id' => $round->id,
                        'session_player_id' => $ranking['session_player_id'],
                    ],
                    [
                        'correct_answers_count' => $ranking['correct_count'],
                        'total_response_time_ms' => $ranking['total_time'],
                        'rank' => $rank,
                        'is_qualified' => $isQualified,
                    ],
                );

                if (! $isQualified) {
                    $sessionPlayer = SessionPlayer::find($ranking['session_player_id']);

                    $eliminated[] = $this->eliminatePlayer(
                        $session,
                        $sessionPlayer,
                        $round,
                        null,
                        EliminationReason::Top4Cutoff,
                    );
                }
            }
        });

        // Construire les données de classement avec pseudo
        $rankingsData = RoundRanking::query()
            ->where('session_round_id', $round->id)
            ->orderBy('rank')
            ->with('sessionPlayer.player:id,pseudo')
            ->get()
            ->map(fn (RoundRanking $r) => [
                'session_player_id' => $r->session_player_id,
                'pseudo' => $r->sessionPlayer?->player?->pseudo ?? 'Joueur',
                'correct_answers_count' => $r->correct_answers_count,
                'total_response_time_ms' => $r->total_response_time_ms,
                'rank' => $r->rank,
                'is_qualified' => (bool) $r->is_qualified,
            ])
            ->toArray();

        // Diffuser les événements temps réel
        if (count($eliminated) > 0) {
            $session->refresh();
            $eliminatedData = SessionPlayer::whereIn('id', $eliminated)
                ->with('player:id,pseudo')
                ->get()
                ->map(fn (SessionPlayer $sp) => [
                    'pseudo' => $sp->player->pseudo ?? 'Joueur',
                    'reason' => $sp->elimination_reason?->value ?? 'top4_cutoff',
                ])->toArray();

            event(new PlayerEliminated($session, $eliminatedData, $session->players_remaining, $session->jackpot, $eliminated));
            event(new JackpotUpdated($session, $session->jackpot, $session->players_remaining));
        }

        event(new Top4Finalized($session, $rankingsData));

        return response()->json([
            'message' => 'Top 4 sélectionné.',
            'eliminated_count' => count($eliminated),
            'eliminated_player_ids' => $eliminated,
            'rankings' => $rankingsData,
        ]);
    }

    // ───────────────────────────────────────────────────────────
    //  Manches 6 & 7 — Duels à tour de rôle
    // ───────────────────────────────────────────────────────────

    /**
     * Configure l'ordre de passage pour une manche duel (manches 6 ou 7).
     */
    #[OA\Post(
        path: '/admin/sessions/{session}/game/setup-turn-order',
        summary: 'Configurer l\'ordre de passage pour les duels',
        security: [['sanctum' => []]],
        tags: ['Game Engine'],
        parameters: [new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['player_order'],
                properties: [
                    new OA\Property(property: 'player_order', type: 'array', items: new OA\Items(type: 'integer'), description: 'IDs des session_players dans l\'ordre'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Ordre configuré'),
            new OA\Response(response: 422, description: 'Manche non compatible'),
        ],
    )]
    public function setupTurnOrder(Request $request, Session $session): JsonResponse
    {
        $round = $session->currentRound;

        if (! $round || ! in_array($round->round_type, [RoundType::DuelJackpot, RoundType::DuelElimination])) {
            return $this->statusError('Cette action n\'est disponible qu\'en manche Duel.');
        }

        $validated = $request->validate([
            'player_order' => ['required', 'array', 'min:2'],
            'player_order.*' => ['integer', 'exists:session_players,id'],
        ]);

        DB::transaction(function () use ($round, $validated) {
            // Supprimer l'ordre précédent
            Round6TurnOrder::query()
                ->where('session_round_id', $round->id)
                ->delete();

            foreach ($validated['player_order'] as $index => $sessionPlayerId) {
                Round6TurnOrder::create([
                    'session_round_id' => $round->id,
                    'session_player_id' => $sessionPlayerId,
                    'turn_order' => $index + 1,
                    'is_active' => true,
                ]);
            }

            // Pour la manche 6, initialiser les cagnottes personnelles
            if ($round->round_type === RoundType::DuelJackpot) {
                Round6PlayerJackpot::query()
                    ->where('session_round_id', $round->id)
                    ->delete();

                foreach ($validated['player_order'] as $sessionPlayerId) {
                    Round6PlayerJackpot::create([
                        'session_round_id' => $round->id,
                        'session_player_id' => $sessionPlayerId,
                        'bonus_count' => 0,
                        'personal_jackpot' => 1000,
                    ]);
                }
            }
        });

        return response()->json([
            'message' => 'Ordre de passage configuré.',
            'turn_order' => Round6TurnOrder::query()
                ->where('session_round_id', $round->id)
                ->orderBy('turn_order')
                ->get(['session_player_id', 'turn_order', 'is_active']),
        ]);
    }

    /**
     * Récupère le prochain joueur dont c'est le tour dans la rotation.
     */
    #[OA\Get(
        path: '/admin/sessions/{session}/game/next-turn',
        summary: 'Récupérer le prochain joueur dans la rotation',
        security: [['sanctum' => []]],
        tags: ['Game Engine'],
        parameters: [new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Prochain joueur'),
            new OA\Response(response: 422, description: 'Manche non compatible'),
        ],
    )]
    public function getNextTurn(Session $session): JsonResponse
    {
        $round = $session->currentRound;

        if (! $round || ! in_array($round->round_type, [RoundType::DuelJackpot, RoundType::DuelElimination])) {
            return $this->statusError('Cette action n\'est disponible qu\'en manche Duel.');
        }

        $activeTurns = Round6TurnOrder::query()
            ->where('session_round_id', $round->id)
            ->where('is_active', true)
            ->orderBy('turn_order')
            ->get();

        if ($activeTurns->isEmpty()) {
            return response()->json(['message' => 'Plus de joueurs actifs.', 'finished' => true]);
        }

        // Trouver le dernier joueur ayant répondu pour déterminer le suivant
        $lastAnsweredPlayerId = PlayerAnswer::query()
            ->whereHas('question', fn ($q) => $q->where('session_round_id', $round->id))
            ->orderByDesc('submitted_at')
            ->value('session_player_id');

        $nextTurn = null;

        if ($lastAnsweredPlayerId) {
            $lastTurnOrder = $activeTurns->firstWhere('session_player_id', $lastAnsweredPlayerId);

            if ($lastTurnOrder) {
                $nextTurn = $activeTurns
                    ->where('turn_order', '>', $lastTurnOrder->turn_order)
                    ->first();
            }
        }

        // Si pas trouvé (début ou rebouclage), prendre le premier actif
        if (! $nextTurn) {
            $nextTurn = $activeTurns->first();
        }

        return response()->json([
            'next_player_id' => $nextTurn->session_player_id,
            'turn_order' => $nextTurn->turn_order,
            'active_players_count' => $activeTurns->count(),
        ]);
    }

    /**
     * Assigne automatiquement les questions de la manche aux joueurs (manches 6/7).
     * Configure l'ordre de passage et pré-attribue chaque question à un joueur.
     */
    #[OA\Post(
        path: '/admin/sessions/{session}/game/assign-duel-questions',
        summary: 'Attribuer les questions aux joueurs pour les manches duel',
        security: [['sanctum' => []]],
        tags: ['Game Engine'],
        parameters: [new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Questions attribuées'),
            new OA\Response(response: 422, description: 'Manche non compatible ou conditions non remplies'),
        ],
    )]
    public function assignDuelQuestions(Session $session): JsonResponse
    {
        $round = $session->currentRound;

        if (! $round || ! in_array($round->round_type, [RoundType::DuelJackpot, RoundType::DuelElimination])) {
            return $this->statusError('Cette action n\'est disponible qu\'en manche Duel.');
        }

        // Vérifier exactement 4 questions
        $questions = Question::query()
            ->where('session_round_id', $round->id)
            ->where('status', QuestionStatus::Pending)
            ->orderBy('display_order')
            ->get();

        if ($questions->count() !== 4) {
            return $this->statusError('Les manches 6 et 7 doivent contenir exactement 4 questions (actuellement : '.$questions->count().').');
        }

        // Récupérer les joueurs actifs
        $activePlayers = SessionPlayer::query()
            ->where('session_id', $session->id)
            ->where('status', SessionPlayerStatus::Active)
            ->with('player:id,pseudo')
            ->get();

        if ($activePlayers->isEmpty() || $activePlayers->count() > 4) {
            return $this->statusError('Il faut entre 1 et 4 joueurs actifs pour cette manche (actuellement : '.$activePlayers->count().').');
        }

        $assignments = [];

        DB::transaction(function () use ($round, $questions, $activePlayers, $session, &$assignments) {
            // Supprimer l'ancien ordre de passage
            Round6TurnOrder::query()
                ->where('session_round_id', $round->id)
                ->delete();

            // Attribuer les questions aux joueurs (tournoi, cyclique si < 4 joueurs)
            foreach ($questions as $index => $question) {
                $playerIndex = $index % $activePlayers->count();
                $player = $activePlayers[$playerIndex];

                $question->update(['assigned_player_id' => $player->id]);

                Round6TurnOrder::create([
                    'session_round_id' => $round->id,
                    'session_player_id' => $player->id,
                    'turn_order' => $index + 1,
                    'is_active' => true,
                ]);

                $assignments[] = [
                    'session_player_id' => $player->id,
                    'pseudo' => $player->player->pseudo,
                    'turn_order' => $index + 1,
                    'question_id' => $question->id,
                ];
            }

            // Pour la manche 6, initialiser les cagnottes personnelles
            if ($round->round_type === RoundType::DuelJackpot) {
                Round6PlayerJackpot::query()
                    ->where('session_round_id', $round->id)
                    ->delete();

                foreach ($activePlayers as $player) {
                    Round6PlayerJackpot::create([
                        'session_round_id' => $round->id,
                        'session_player_id' => $player->id,
                        'bonus_count' => 0,
                        'personal_jackpot' => 1000,
                    ]);
                }
            }
        });

        event(new DuelQuestionsAssigned($session, $assignments));

        return response()->json([
            'message' => 'Questions attribuées aux joueurs.',
            'assignments' => $assignments,
        ]);
    }

    // ───────────────────────────────────────────────────────────
    //  Manche 8 — Finale
    // ───────────────────────────────────────────────────────────

    /**
     * Lance le vote de la finale : marque les joueurs actifs comme finalistes
     * et diffuse l'événement pour que les joueurs puissent voter.
     */
    #[OA\Post(
        path: '/admin/sessions/{session}/game/launch-finale-vote',
        summary: 'Lancer le vote de la finale (continuer/abandonner)',
        security: [['sanctum' => []]],
        tags: ['Game Engine'],
        parameters: [new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Vote lancé'),
            new OA\Response(response: 422, description: 'Pas en finale'),
        ],
    )]
    public function launchFinaleVote(Session $session): JsonResponse
    {
        $round = $session->currentRound;

        if (! $round || $round->round_type !== RoundType::Finale) {
            return $this->statusError('Cette action n\'est disponible qu\'en finale.');
        }

        // Marquer tous les joueurs encore actifs comme finalistes
        $activePlayers = SessionPlayer::query()
            ->where('session_id', $session->id)
            ->where('status', SessionPlayerStatus::Active)
            ->get();

        foreach ($activePlayers as $player) {
            $player->update(['status' => SessionPlayerStatus::Finalist]);
        }

        // Supprimer d'éventuels anciens choix
        FinaleChoice::query()->where('session_id', $session->id)->delete();

        $finalists = SessionPlayer::query()
            ->where('session_id', $session->id)
            ->where('status', SessionPlayerStatus::Finalist)
            ->with('player:id,pseudo')
            ->get()
            ->map(fn ($sp) => [
                'session_player_id' => $sp->id,
                'pseudo' => $sp->player?->pseudo ?? 'Joueur',
            ])->toArray();

        event(new FinaleVoteLaunched($session, $finalists));

        return response()->json([
            'message' => 'Vote de la finale lancé.',
            'finalists' => $finalists,
        ]);
    }

    /**
     * Révèle les choix des finalistes (continuer / abandonner).
     */
    #[OA\Post(
        path: '/admin/sessions/{session}/game/reveal-finale-choices',
        summary: 'Révéler les choix des finalistes',
        security: [['sanctum' => []]],
        tags: ['Game Engine'],
        parameters: [new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Choix révélés avec scénario'),
            new OA\Response(response: 422, description: 'Pas en finale ou choix manquants'),
        ],
    )]
    public function revealFinaleChoices(Session $session): JsonResponse
    {
        $round = $session->currentRound;

        if (! $round || $round->round_type !== RoundType::Finale) {
            return $this->statusError('Cette action n\'est disponible qu\'en finale.');
        }

        $choices = FinaleChoice::query()
            ->where('session_id', $session->id)
            ->with('sessionPlayer.player:id,pseudo')
            ->get();

        $finalistCount = SessionPlayer::query()
            ->where('session_id', $session->id)
            ->where('status', SessionPlayerStatus::Finalist)
            ->count();

        if ($choices->count() < $finalistCount) {
            return $this->statusError('Tous les finalistes n\'ont pas encore fait leur choix ('.$choices->count().'/'.$finalistCount.').');
        }

        FinaleChoice::query()
            ->where('session_id', $session->id)
            ->update(['revealed' => true]);

        $choices = $choices->fresh();

        $bothContinue = $choices->every(fn ($c) => $c->choice === FinaleChoiceType::Continue);
        $bothAbandon = $choices->every(fn ($c) => $c->choice === FinaleChoiceType::Abandon);
        $someAbandon = ! $bothContinue && ! $bothAbandon;

        $choicesData = $choices->map(fn ($c) => [
            'session_player_id' => $c->session_player_id,
            'choice' => $c->choice->value,
            'pseudo' => $c->sessionPlayer?->player?->pseudo ?? 'Joueur',
        ])->toArray();

        $scenario = $bothAbandon ? 'all_abandon' : ($someAbandon ? 'some_abandon' : 'all_continue');

        $continuers = $choices->filter(fn ($c) => $c->choice === FinaleChoiceType::Continue)->count();

        event(new FinaleChoicesRevealed($session, $choicesData, $scenario));

        return response()->json([
            'message' => 'Choix révélés.',
            'choices' => $choicesData,
            'scenario' => $scenario,
            'continuers_count' => $continuers,
            'needs_final_question' => $continuers > 0,
        ]);
    }

    /**
     * Résout la finale et calcule les gains.
     */
    #[OA\Post(
        path: '/admin/sessions/{session}/game/resolve-finale',
        summary: 'Résoudre la finale et calculer les gains',
        security: [['sanctum' => []]],
        tags: ['Game Engine'],
        parameters: [new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Finale résolue avec résultats'),
            new OA\Response(response: 422, description: 'Pas en finale ou choix manquants'),
        ],
    )]
    public function resolveFinale(Session $session): JsonResponse
    {
        $round = $session->currentRound;

        if (! $round || $round->round_type !== RoundType::Finale) {
            return $this->statusError('Cette action n\'est disponible qu\'en finale.');
        }

        $choices = FinaleChoice::query()
            ->where('session_id', $session->id)
            ->get();

        $finalistCount = SessionPlayer::query()
            ->where('session_id', $session->id)
            ->where('status', SessionPlayerStatus::Finalist)
            ->count();

        if ($choices->count() < $finalistCount) {
            return $this->statusError('Tous les finalistes doivent avoir fait leur choix.');
        }

        $results = [];

        DB::transaction(function () use ($session, $round, $choices, &$results) {
            $allContinue = $choices->every(fn ($c) => $c->choice === FinaleChoiceType::Continue);
            $allAbandon = $choices->every(fn ($c) => $c->choice === FinaleChoiceType::Abandon);
            $continuers = $choices->filter(fn ($c) => $c->choice === FinaleChoiceType::Continue);
            $abandoners = $choices->filter(fn ($c) => $c->choice === FinaleChoiceType::Abandon);

            if ($allAbandon) {
                // Tous abandonnent → 5000 chacun
                $results = $this->resolveFinaleAllAbandon($session, $round, $choices);
            } elseif ($allContinue) {
                // Tous continuent → évaluer les réponses
                $results = $this->resolveFinaleAllContinue($session, $round, $choices);
            } else {
                // Mixte : certains abandonnent, certains continuent
                $results = $this->resolveFinaleMixed($session, $round, $continuers, $abandoners);
            }

            // Mettre à jour le statut des joueurs
            foreach ($results as $result) {
                $sessionPlayer = SessionPlayer::find($result['session_player_id']);
                $sessionPlayer->update([
                    'status' => $result['is_winner'] ? SessionPlayerStatus::FinalistWinner : SessionPlayerStatus::FinalistLoser,
                    'final_gain' => $result['final_gain'],
                    'personal_jackpot' => $result['final_gain'],
                ]);
            }
        });

        // Terminer la session et diffuser GameEnded
        $session->update([
            'status' => SessionStatus::Ended,
            'ended_at' => now(),
        ]);

        $winners = collect($results)->map(function ($r) {
            $sp = SessionPlayer::with('player:id,pseudo')->find($r['session_player_id']);

            return [
                'pseudo' => $sp?->player?->pseudo ?? 'Joueur',
                'final_gain' => $r['final_gain'],
            ];
        })->toArray();

        event(new GameEnded($session, $session->jackpot, $winners));

        return response()->json([
            'message' => 'Finale résolue.',
            'results' => $results,
        ]);
    }

    // ───────────────────────────────────────────────────────────
    //  Navigation et fin
    // ───────────────────────────────────────────────────────────

    /**
     * Révèle la bonne réponse.
     */
    #[OA\Post(
        path: '/admin/sessions/{session}/game/reveal-answer',
        summary: 'Révéler la bonne réponse de la question courante',
        security: [['sanctum' => []]],
        tags: ['Game Engine'],
        parameters: [new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Réponse révélée'),
            new OA\Response(response: 422, description: 'Aucune question clôturée'),
        ],
    )]
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

        $choicesData = $question->choices->map(fn ($c) => [
            'id' => $c->id,
            'label' => $c->label,
            'is_correct' => $c->is_correct,
        ])->toArray();

        event(new AnswerRevealed($session, $question->id, $question->correct_answer, $choicesData));

        return response()->json([
            'message' => 'Réponse révélée.',
            'correct_answer' => $question->correct_answer,
        ]);
    }

    /**
     * Passe à la manche suivante active.
     */
    #[OA\Post(
        path: '/admin/sessions/{session}/game/next-round',
        summary: 'Passer à la manche suivante',
        security: [['sanctum' => []]],
        tags: ['Game Engine'],
        parameters: [new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Manche suivante démarrée ou session terminée'),
            new OA\Response(response: 422, description: 'Session pas en cours'),
        ],
    )]
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

            event(new RoundEnded(
                $session,
                $currentRound->round_number,
                $currentRound->name,
                $session->players_remaining,
                $session->jackpot,
            ));
        }

        $nextRound = $session->rounds()
            ->where('is_active', true)
            ->where('display_order', '>', $currentRound?->display_order ?? 0)
            ->orderBy('display_order')
            ->first();

        if (! $nextRound) {
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

        event(new RoundStarted(
            $session,
            $nextRound->round_number,
            $nextRound->name,
            $nextRound->round_type->value,
            $nextRound->rules_description,
        ));

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
    #[OA\Post(
        path: '/admin/sessions/{session}/game/end',
        summary: 'Terminer la session manuellement',
        security: [['sanctum' => []]],
        tags: ['Game Engine'],
        parameters: [new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Session terminée'),
            new OA\Response(response: 422, description: 'Session pas en cours'),
        ],
    )]
    public function endSession(Session $session): JsonResponse
    {
        if (! in_array($session->status, [SessionStatus::InProgress, SessionStatus::Paused])) {
            return $this->statusError('La session n\'est pas en cours.');
        }

        $session->update([
            'status' => SessionStatus::Ended,
            'ended_at' => now(),
        ]);

        $winners = FinalResult::query()
            ->where('session_id', $session->id)
            ->with('sessionPlayer.player:id,pseudo')
            ->get()
            ->map(fn ($r) => [
                'pseudo' => $r->sessionPlayer->player->pseudo ?? 'Joueur',
                'final_gain' => $r->final_gain,
            ])->toArray();

        event(new GameEnded($session, $session->jackpot, $winners));

        return response()->json(['message' => 'Session terminée.']);
    }

    // ═══════════════════════════════════════════════════════════
    //  Méthodes privées
    // ═══════════════════════════════════════════════════════════

    /**
     * Détermine quels joueurs doivent répondre à la question.
     * En duels, seul le joueur assigné répond.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, SessionPlayer>
     */
    private function getRespondingPlayers(Session $session, Question $question, SessionRound $round): \Illuminate\Database\Eloquent\Collection
    {
        if (in_array($round->round_type, [RoundType::DuelJackpot, RoundType::DuelElimination])) {
            // En duels, seul le joueur assigné doit répondre
            if ($question->assigned_player_id) {
                return SessionPlayer::query()
                    ->where('id', $question->assigned_player_id)
                    ->where('status', SessionPlayerStatus::Active)
                    ->get();
            }
        }

        // En finale, seuls les finalistes ayant choisi "continuer" répondent
        if ($round->round_type === RoundType::Finale) {
            $continuerIds = FinaleChoice::query()
                ->where('session_id', $session->id)
                ->where('choice', FinaleChoiceType::Continue)
                ->pluck('session_player_id');

            return SessionPlayer::query()
                ->where('session_id', $session->id)
                ->where('status', SessionPlayerStatus::Finalist)
                ->whereIn('id', $continuerIds)
                ->get();
        }

        $query = SessionPlayer::query()
            ->where('session_id', $session->id)
            ->where('status', SessionPlayerStatus::Active);

        // En manche 4 (RoundSkip), exclure les joueurs ayant passé la manche
        if ($round->round_type === RoundType::RoundSkip) {
            $skippedIds = \App\Models\RoundSkip::query()
                ->where('session_round_id', $round->id)
                ->pluck('session_player_id');

            $query->whereNotIn('id', $skippedIds);
        }

        return $query->get();
    }

    /**
     * Vérifie la réponse d'un joueur pour une question principale.
     */
    private function checkAnswer(Question $question, PlayerAnswer $answer): bool
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
     * Vérifie la réponse d'un joueur pour une question de seconde chance.
     */
    private function checkSecondChanceAnswer(SecondChanceQuestion $scQuestion, PlayerAnswer $answer): bool
    {
        return match ($scQuestion->answer_type) {
            AnswerType::Qcm => $answer->selected_sc_choice_id !== null
                && $scQuestion->choices()->where('id', $answer->selected_sc_choice_id)->where('is_correct', true)->exists(),

            AnswerType::Text => $answer->answer_value !== null
                && strtolower(trim($this->removeAccents($answer->answer_value)))
                === strtolower(trim($this->removeAccents($scQuestion->correct_answer))),

            AnswerType::Number => $answer->answer_value !== null
                && is_numeric($answer->answer_value)
                && abs((float) $answer->answer_value - (float) $scQuestion->correct_answer) <= 0.0001,

            default => false,
        };
    }

    // ───────────────────────────────────────────────────────────
    //  Logique Seconde Chance (manche 3)
    // ───────────────────────────────────────────────────────────

    /**
     * Évalue les réponses en manche SecondChance.
     * Ne fait PAS d'élimination directe — signale qu'une seconde chance est nécessaire.
     *
     * @return list<int>
     */
    private function handleSecondChanceEvaluation(
        Session $session,
        Question $question,
        SessionRound $round,
        bool &$needsSecondChance,
        array &$failedPlayerIds,
    ): array {
        $wrongAnswerPlayerIds = PlayerAnswer::query()
            ->where('question_id', $question->id)
            ->where('is_correct', false)
            ->where('is_second_chance', false)
            ->pluck('session_player_id')
            ->toArray();

        // Filtrer les joueurs encore actifs
        $failedPlayerIds = SessionPlayer::query()
            ->whereIn('id', $wrongAnswerPlayerIds)
            ->where('status', SessionPlayerStatus::Active)
            ->pluck('id')
            ->toArray();

        $needsSecondChance = ! empty($failedPlayerIds) && $question->secondChanceQuestion !== null;

        return []; // Pas d'élimination directe en manche 3
    }

    // ───────────────────────────────────────────────────────────
    //  Logique Duel Jackpot (manche 6)
    // ───────────────────────────────────────────────────────────

    /**
     * Traite la clôture d'une question en manche Duel Jackpot.
     * Bonne réponse = +1000 bonus. Mauvaise = élimination avec cagnotte perso.
     *
     * @return list<int>
     */
    private function handleDuelJackpotClose(Session $session, Question $question, SessionRound $round): array
    {
        $eliminated = [];

        if (! $question->assigned_player_id) {
            return [];
        }

        $answer = PlayerAnswer::query()
            ->where('session_player_id', $question->assigned_player_id)
            ->where('question_id', $question->id)
            ->where('is_second_chance', false)
            ->first();

        if (! $answer) {
            return [];
        }

        $sessionPlayer = SessionPlayer::find($question->assigned_player_id);

        if (! $sessionPlayer || $sessionPlayer->status !== SessionPlayerStatus::Active) {
            return [];
        }

        $playerJackpot = Round6PlayerJackpot::query()
            ->where('session_round_id', $round->id)
            ->where('session_player_id', $sessionPlayer->id)
            ->first();

        if ($answer->is_correct) {
            // Bonne réponse : +1000 bonus à la cagnotte personnelle
            if ($playerJackpot) {
                $playerJackpot->increment('bonus_count');
                $playerJackpot->increment('personal_jackpot', 1000);

                // Synchroniser la cagnotte personnelle sur le SessionPlayer
                $sessionPlayer->update(['personal_jackpot' => $playerJackpot->personal_jackpot]);
            }

            $this->logJackpotTransaction(
                $session,
                $sessionPlayer,
                $round,
                JackpotTransactionType::Round6Bonus,
                1000,
                'Bonus +1000 pour bonne réponse en manche 6',
            );
        } else {
            // Mauvaise réponse ou timeout : éliminé, repart avec sa cagnotte perso
            $departedWith = $playerJackpot?->personal_jackpot ?? 1000;

            if ($playerJackpot) {
                $playerJackpot->update(['departed_with' => $departedWith]);
            }

            $sessionPlayer->update([
                'status' => SessionPlayerStatus::Eliminated,
                'eliminated_at' => now(),
                'elimination_reason' => $answer->is_timeout ? EliminationReason::Timeout : EliminationReason::DuelLost,
                'eliminated_in_round_id' => $round->id,
                'final_gain' => $departedWith,
                'personal_jackpot' => $departedWith,
            ]);

            Elimination::create([
                'session_player_id' => $sessionPlayer->id,
                'session_round_id' => $round->id,
                'question_id' => $question->id,
                'reason' => $answer->is_timeout ? EliminationReason::Timeout : EliminationReason::DuelLost,
                'capital_transferred' => 0,
                'eliminated_at' => now(),
            ]);

            // Désactiver dans l'ordre de passage
            Round6TurnOrder::query()
                ->where('session_round_id', $round->id)
                ->where('session_player_id', $sessionPlayer->id)
                ->update(['is_active' => false]);

            $this->logJackpotTransaction(
                $session,
                $sessionPlayer,
                $round,
                JackpotTransactionType::Round6Departure,
                -$departedWith,
                "Départ manche 6 avec {$departedWith} points",
            );

            $session->decrement('players_remaining');
            $eliminated[] = $sessionPlayer->id;
        }

        return $eliminated;
    }

    // ───────────────────────────────────────────────────────────
    //  Logique Duel Elimination (manche 7)
    // ───────────────────────────────────────────────────────────

    /**
     * Traite la clôture d'une question en manche Duel Élimination.
     * Mauvaise réponse = élimination directe, mais le joueur accède quand même à la finale.
     *
     * @return list<int>
     */
    private function handleDuelEliminationClose(Session $session, Question $question, SessionRound $round): array
    {
        $eliminated = [];

        if (! $question->assigned_player_id) {
            return [];
        }

        $answer = PlayerAnswer::query()
            ->where('session_player_id', $question->assigned_player_id)
            ->where('question_id', $question->id)
            ->where('is_second_chance', false)
            ->first();

        if (! $answer || $answer->is_correct) {
            return [];
        }

        $sessionPlayer = SessionPlayer::find($question->assigned_player_id);

        if (! $sessionPlayer || $sessionPlayer->status !== SessionPlayerStatus::Active) {
            return [];
        }

        // En manche 7, le perdant est éliminé.
        // Quand il ne reste que 2 joueurs actifs, ceux-ci deviennent Finalist.
        $sessionPlayer->update([
            'status' => SessionPlayerStatus::Eliminated,
            'eliminated_at' => now(),
            'elimination_reason' => $answer->is_timeout ? EliminationReason::Timeout : EliminationReason::DuelLost,
            'eliminated_in_round_id' => $round->id,
        ]);

        Elimination::create([
            'session_player_id' => $sessionPlayer->id,
            'session_round_id' => $round->id,
            'question_id' => $question->id,
            'reason' => $answer->is_timeout ? EliminationReason::Timeout : EliminationReason::DuelLost,
            'capital_transferred' => 0,
            'eliminated_at' => now(),
        ]);

        Round6TurnOrder::query()
            ->where('session_round_id', $round->id)
            ->where('session_player_id', $sessionPlayer->id)
            ->update(['is_active' => false]);

        $session->decrement('players_remaining');
        $eliminated[] = $sessionPlayer->id;

        // Vérifier s'il ne reste que 2 joueurs → les marquer comme finalistes
        // (la promotion effective en Finalist se fait via launchFinaleVote en manche 8)
        $remainingActive = Round6TurnOrder::query()
            ->where('session_round_id', $round->id)
            ->where('is_active', true)
            ->count();

        return $eliminated;
    }

    // ───────────────────────────────────────────────────────────
    //  Logique Finale (manche 8) — N joueurs
    // ───────────────────────────────────────────────────────────

    /**
     * Tous les finalistes abandonnent → 5000 chacun.
     */
    private function resolveFinaleAllAbandon(Session $session, SessionRound $round, $choices): array
    {
        $results = [];

        foreach ($choices as $index => $choice) {
            $gain = 5000;

            FinalResult::updateOrCreate(
                [
                    'session_id' => $session->id,
                    'session_player_id' => $choice->session_player_id,
                ],
                [
                    'finale_scenario' => FinaleScenario::BothAbandon,
                    'final_gain' => $gain,
                    'is_winner' => false,
                    'position' => $index + 1,
                ]
            );

            $this->logJackpotTransaction(
                $session,
                SessionPlayer::find($choice->session_player_id),
                $round,
                JackpotTransactionType::FinaleAbandonShare,
                -$gain,
                'Finale : abandon — 5000',
            );

            $results[] = [
                'session_player_id' => $choice->session_player_id,
                'finale_scenario' => FinaleScenario::BothAbandon,
                'final_gain' => $gain,
                'is_winner' => false,
                'position' => $index + 1,
            ];
        }

        return $results;
    }

    /**
     * Tous les finalistes continuent → évaluer les réponses à la question finale.
     *  - Tous ont bon → partage
     *  - Certains ont bon → les gagnants se partagent le jackpot
     *  - Aucun n'a bon → 2000 chacun
     */
    private function resolveFinaleAllContinue(Session $session, SessionRound $round, $choices): array
    {
        $results = [];
        $finalQuestion = $round->questions()->orderByDesc('display_order')->first();

        $playerResults = [];
        foreach ($choices as $choice) {
            $answer = null;
            if ($finalQuestion) {
                $answer = PlayerAnswer::query()
                    ->where('session_player_id', $choice->session_player_id)
                    ->where('question_id', $finalQuestion->id)
                    ->where('is_second_chance', false)
                    ->first();
            }
            $playerResults[] = [
                'session_player_id' => $choice->session_player_id,
                'is_correct' => $answer?->is_correct ?? false,
            ];
        }

        $correctCount = collect($playerResults)->where('is_correct', true)->count();
        $total = count($playerResults);

        if ($correctCount > 0 && $correctCount === $total) {
            // Tous ont bon → partage équitable
            $share = (int) floor($session->jackpot / $total);
            foreach ($playerResults as $index => $pr) {
                FinalResult::updateOrCreate(
                    [
                        'session_id' => $session->id,
                        'session_player_id' => $pr['session_player_id'],
                    ],
                    [
                        'finale_scenario' => FinaleScenario::BothContinueBothWin,
                        'final_gain' => $share,
                        'is_winner' => true,
                        'position' => $index + 1,
                    ]
                );
                $this->logJackpotTransaction(
                    $session, SessionPlayer::find($pr['session_player_id']), $round,
                    JackpotTransactionType::FinaleShare, -$share,
                    "Finale : partage — {$share} chacun",
                );
                $results[] = [
                    'session_player_id' => $pr['session_player_id'],
                    'finale_scenario' => FinaleScenario::BothContinueBothWin,
                    'final_gain' => $share,
                    'is_winner' => true,
                    'position' => $index + 1,
                ];
            }
        } elseif ($correctCount > 0) {
            // Certains ont bon → les gagnants se partagent le jackpot
            $share = (int) floor($session->jackpot / $correctCount);
            foreach ($playerResults as $index => $pr) {
                $gain = $pr['is_correct'] ? $share : 0;
                FinalResult::updateOrCreate(
                    [
                        'session_id' => $session->id,
                        'session_player_id' => $pr['session_player_id'],
                    ],
                    [
                        'finale_scenario' => FinaleScenario::BothContinueOneWins,
                        'final_gain' => $gain,
                        'is_winner' => $pr['is_correct'],
                        'position' => $pr['is_correct'] ? $index + 1 : $total,
                    ]
                );
                if ($gain > 0) {
                    $this->logJackpotTransaction(
                        $session, SessionPlayer::find($pr['session_player_id']), $round,
                        JackpotTransactionType::FinaleWin, -$gain,
                        "Finale : gagnant remporte {$gain}",
                    );
                }
                $results[] = [
                    'session_player_id' => $pr['session_player_id'],
                    'finale_scenario' => FinaleScenario::BothContinueOneWins,
                    'final_gain' => $gain,
                    'is_winner' => $pr['is_correct'],
                    'position' => $pr['is_correct'] ? $index + 1 : $total,
                ];
            }
        } else {
            // Aucun n'a bon → 2000 chacun
            foreach ($playerResults as $index => $pr) {
                $gain = 2000;
                FinalResult::updateOrCreate(
                    [
                        'session_id' => $session->id,
                        'session_player_id' => $pr['session_player_id'],
                    ],
                    [
                        'finale_scenario' => FinaleScenario::BothContinueBothFail,
                        'final_gain' => $gain,
                        'is_winner' => false,
                        'position' => $index + 1,
                    ]
                );
                $results[] = [
                    'session_player_id' => $pr['session_player_id'],
                    'finale_scenario' => FinaleScenario::BothContinueBothFail,
                    'final_gain' => $gain,
                    'is_winner' => false,
                    'position' => $index + 1,
                ];
            }
        }

        return $results;
    }

    /**
     * Mixte : certains abandonnent, certains continuent.
     * - Abandonneurs → 2000 chacun
     * - Continueurs → évaluer les réponses, ceux qui ont bon se partagent le jackpot
     */
    private function resolveFinaleMixed(Session $session, SessionRound $round, $continuers, $abandoners): array
    {
        $results = [];

        // Abandonneurs : 2000 chacun
        foreach ($abandoners as $index => $choice) {
            $gain = 2000;
            FinalResult::updateOrCreate(
                [
                    'session_id' => $session->id,
                    'session_player_id' => $choice->session_player_id,
                ],
                [
                    'finale_scenario' => FinaleScenario::OneAbandons,
                    'final_gain' => $gain,
                    'is_winner' => false,
                    'position' => 0,
                ]
            );
            $this->logJackpotTransaction(
                $session, SessionPlayer::find($choice->session_player_id), $round,
                JackpotTransactionType::FinaleAbandonShare, -$gain,
                'Finale : abandonneur repart avec 2000',
            );
            $results[] = [
                'session_player_id' => $choice->session_player_id,
                'finale_scenario' => FinaleScenario::OneAbandons,
                'final_gain' => $gain,
                'is_winner' => false,
                'position' => 0,
            ];
        }

        // Continueurs : évaluer les réponses
        $finalQuestion = $round->questions()->orderByDesc('display_order')->first();
        $playerResults = [];
        foreach ($continuers as $choice) {
            $answer = null;
            if ($finalQuestion) {
                $answer = PlayerAnswer::query()
                    ->where('session_player_id', $choice->session_player_id)
                    ->where('question_id', $finalQuestion->id)
                    ->where('is_second_chance', false)
                    ->first();
            }
            $playerResults[] = [
                'session_player_id' => $choice->session_player_id,
                'is_correct' => $answer?->is_correct ?? false,
            ];
        }

        $correctCount = collect($playerResults)->where('is_correct', true)->count();

        if ($correctCount > 0) {
            $share = (int) floor($session->jackpot / $correctCount);
            foreach ($playerResults as $index => $pr) {
                $gain = $pr['is_correct'] ? $share : 0;
                FinalResult::updateOrCreate(
                    [
                        'session_id' => $session->id,
                        'session_player_id' => $pr['session_player_id'],
                    ],
                    [
                        'finale_scenario' => FinaleScenario::OneAbandons,
                        'final_gain' => $gain,
                        'is_winner' => $pr['is_correct'],
                        'position' => $pr['is_correct'] ? 1 : 0,
                    ]
                );
                if ($gain > 0) {
                    $this->logJackpotTransaction(
                        $session, SessionPlayer::find($pr['session_player_id']), $round,
                        JackpotTransactionType::FinaleWin, -$gain,
                        "Finale : gagnant remporte {$gain}",
                    );
                }
                $results[] = [
                    'session_player_id' => $pr['session_player_id'],
                    'finale_scenario' => FinaleScenario::OneAbandons,
                    'final_gain' => $gain,
                    'is_winner' => $pr['is_correct'],
                    'position' => $pr['is_correct'] ? 1 : 0,
                ];
            }
        } else {
            // Personne n'a bon → 0 chacun pour les continueurs
            foreach ($playerResults as $pr) {
                FinalResult::updateOrCreate(
                    [
                        'session_id' => $session->id,
                        'session_player_id' => $pr['session_player_id'],
                    ],
                    [
                        'finale_scenario' => FinaleScenario::OneAbandons,
                        'final_gain' => 0,
                        'is_winner' => false,
                        'position' => 0,
                    ]
                );
                $results[] = [
                    'session_player_id' => $pr['session_player_id'],
                    'finale_scenario' => FinaleScenario::OneAbandons,
                    'final_gain' => 0,
                    'is_winner' => false,
                    'position' => 0,
                ];
            }
        }

        return $results;
    }

    // ───────────────────────────────────────────────────────────
    //  Élimination et traçabilité
    // ───────────────────────────────────────────────────────────

    /**
     * Applique les éliminations directes (manches 1, 2, 4).
     * En manche 4 (RoundSkip), les joueurs ayant "passé" ne sont PAS éliminés
     * (c'est géré par le Player/GameController::passManche).
     *
     * @return list<int> IDs des session_players éliminés
     */
    private function applyDirectEliminations(Session $session, Question $question, SessionRound $round): array
    {
        $directEliminationRounds = [
            RoundType::SuddenDeath,
            RoundType::Hint,
            RoundType::RoundSkip,
        ];

        if (! in_array($round->round_type, $directEliminationRounds)) {
            // Manche 5 → pas d'élimination par question, c'est en fin de manche (finalizeTop4)
            return [];
        }

        $wrongAnswerPlayerIds = PlayerAnswer::query()
            ->where('question_id', $question->id)
            ->where('is_correct', false)
            ->where('is_second_chance', false)
            ->pluck('session_player_id')
            ->toArray();

        if (empty($wrongAnswerPlayerIds)) {
            return [];
        }

        $eliminated = [];

        foreach ($wrongAnswerPlayerIds as $sessionPlayerId) {
            $sessionPlayer = SessionPlayer::find($sessionPlayerId);

            if (! $sessionPlayer || $sessionPlayer->status !== SessionPlayerStatus::Active) {
                continue;
            }

            // En manche 4 (RoundSkip), vérifier si le joueur a passé sa manche → il n'est pas éliminé
            if ($round->round_type === RoundType::RoundSkip) {
                $hasSkipped = \App\Models\RoundSkip::query()
                    ->where('session_player_id', $sessionPlayerId)
                    ->where('session_round_id', $round->id)
                    ->exists();

                if ($hasSkipped) {
                    continue;
                }
            }

            $reason = PlayerAnswer::query()
                ->where('session_player_id', $sessionPlayerId)
                ->where('question_id', $question->id)
                ->value('is_timeout')
                ? EliminationReason::Timeout
                : EliminationReason::WrongAnswer;

            $eliminated[] = $this->eliminatePlayer($session, $sessionPlayer, $round, $question, $reason);
        }

        return $eliminated;
    }

    /**
     * Élimine un joueur et transfère son capital à la cagnotte.
     */
    private function eliminatePlayer(
        Session $session,
        SessionPlayer $sessionPlayer,
        SessionRound $round,
        ?Question $question,
        EliminationReason $reason,
    ): int {
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
            'question_id' => $question?->id,
            'reason' => $reason,
            'capital_transferred' => $capitalTransferred,
            'eliminated_at' => now(),
        ]);

        $this->logJackpotTransaction(
            $session,
            $sessionPlayer,
            $round,
            JackpotTransactionType::Elimination,
            $capitalTransferred,
            "Élimination ({$reason->value}) — {$capitalTransferred} transférés à la cagnotte",
        );

        $session->increment('jackpot', $capitalTransferred);
        $session->decrement('players_remaining');

        return $sessionPlayer->id;
    }

    /**
     * Enregistre une transaction de cagnotte pour la traçabilité.
     */
    private function logJackpotTransaction(
        Session $session,
        ?SessionPlayer $sessionPlayer,
        SessionRound $round,
        JackpotTransactionType $type,
        int $amount,
        string $description,
    ): void {
        $jackpotBefore = $session->jackpot;

        JackpotTransaction::create([
            'session_id' => $session->id,
            'session_player_id' => $sessionPlayer?->id,
            'session_round_id' => $round->id,
            'transaction_type' => $type,
            'amount' => $amount,
            'jackpot_before' => $jackpotBefore,
            'jackpot_after' => $jackpotBefore + $amount,
            'description' => $description,
        ]);
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
