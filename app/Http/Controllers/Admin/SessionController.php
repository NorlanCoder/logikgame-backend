<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoundStatus;
use App\Enums\RoundType;
use App\Enums\SessionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSessionRequest;
use App\Http\Requests\Admin\UpdateSessionRequest;
use App\Http\Resources\SessionResource;
use App\Models\Session;
use App\Models\SessionRound;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use OpenApi\Attributes as OA;

class SessionController extends Controller
{
    #[OA\Get(
        path: '/admin/sessions',
        summary: 'Lister les sessions',
        description: 'Retourne toutes les sessions de l\'admin connecté.',
        security: [['sanctum' => []]],
        tags: ['Sessions'],
        responses: [
            new OA\Response(response: 200, description: 'Liste des sessions'),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $sessions = Session::query()
            ->where('admin_id', $request->user()->id)
            ->withCount(['registrations', 'rounds'])
            ->orderByDesc('scheduled_at')
            ->get();

        return SessionResource::collection($sessions);
    }

    #[OA\Post(
        path: '/admin/sessions',
        summary: 'Créer une session',
        description: 'Crée une nouvelle session de jeu avec 8 manches par défaut.',
        security: [['sanctum' => []]],
        tags: ['Sessions'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['name', 'scheduled_at', 'max_players'],
                    properties: [
                        new OA\Property(property: 'name', type: 'string', example: 'LOGIK S1E01'),
                        new OA\Property(property: 'description', type: 'string', nullable: true),
                        new OA\Property(property: 'scheduled_at', type: 'string', format: 'date-time'),
                        new OA\Property(property: 'max_players', type: 'integer', example: 100),
                        new OA\Property(property: 'cover_image', type: 'string', format: 'binary', nullable: true, description: 'Image de couverture (max 5Mo)'),
                        new OA\Property(property: 'registration_opens_at', type: 'string', format: 'date-time', nullable: true, description: 'Début des inscriptions'),
                        new OA\Property(property: 'registration_closes_at', type: 'string', format: 'date-time', nullable: true, description: 'Fin des inscriptions (doit être après registration_opens_at)'),
                        new OA\Property(property: 'preselection_opens_at', type: 'string', format: 'date-time', nullable: true, description: 'Début de la pré-sélection'),
                        new OA\Property(property: 'preselection_closes_at', type: 'string', format: 'date-time', nullable: true, description: 'Fin de la pré-sélection (doit être après preselection_opens_at)'),
                        new OA\Property(property: 'reconnection_delay', type: 'integer', nullable: true, description: 'Délai de reconnexion en secondes (5-120)'),
                    ],
                ),
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Session créée'),
            new OA\Response(response: 422, description: 'Validation échouée'),
        ],
    )]
    public function store(StoreSessionRequest $request): JsonResponse
    {
        $data = collect($request->validated())->except('cover_image')->toArray();

        if ($request->hasFile('cover_image')) {
            $data['cover_image_url'] = $request->file('cover_image')->store('media/sessions', 'public');
        }

        $session = Session::create([
            ...$data,
            'admin_id' => $request->user()->id,
            'status' => SessionStatus::Draft,
        ]);

        $this->createDefaultRounds($session);

        return response()->json(
            new SessionResource($session->load('rounds')),
            201
        );
    }

    #[OA\Get(
        path: '/admin/sessions/{session}',
        summary: 'Détail d\'une session',
        description: 'Retourne le détail complet d\'une session avec ses manches.',
        security: [['sanctum' => []]],
        tags: ['Sessions'],
        parameters: [
            new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Détail session'),
            new OA\Response(response: 404, description: 'Session introuvable'),
        ],
    )]
    public function show(Session $session): SessionResource
    {
        $session->load([
            'rounds' => fn ($q) => $q->withCount('questions'),
            'currentRound',
        ])->loadCount(['registrations', 'sessionPlayers']);

        return new SessionResource($session);
    }

    #[OA\Put(
        path: '/admin/sessions/{session}',
        summary: 'Modifier une session',
        description: 'Met à jour une session (statut Draft ou RegistrationOpen uniquement).',
        security: [['sanctum' => []]],
        tags: ['Sessions'],
        parameters: [
            new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'name', type: 'string'),
                        new OA\Property(property: 'description', type: 'string'),
                        new OA\Property(property: 'scheduled_at', type: 'string', format: 'date-time'),
                        new OA\Property(property: 'max_players', type: 'integer'),
                        new OA\Property(property: 'cover_image', type: 'string', format: 'binary', nullable: true, description: 'Image de couverture (max 5Mo)'),
                        new OA\Property(property: 'remove_cover_image', type: 'boolean', nullable: true, description: 'Supprimer l\'image de couverture existante'),
                        new OA\Property(property: 'registration_opens_at', type: 'string', format: 'date-time', nullable: true),
                        new OA\Property(property: 'registration_closes_at', type: 'string', format: 'date-time', nullable: true),
                        new OA\Property(property: 'preselection_opens_at', type: 'string', format: 'date-time', nullable: true),
                        new OA\Property(property: 'preselection_closes_at', type: 'string', format: 'date-time', nullable: true),
                        new OA\Property(property: 'reconnection_delay', type: 'integer', nullable: true, description: 'Délai de reconnexion en secondes (5-120)'),
                    ],
                ),
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Session modifiée'),
            new OA\Response(response: 422, description: 'Statut invalide'),
        ],
    )]
    public function update(UpdateSessionRequest $request, Session $session): JsonResponse
    {
        if (in_array($session->status, [SessionStatus::Ended, SessionStatus::Cancelled])) {
            return response()->json([
                'message' => 'Une session terminée ou annulée ne peut plus être modifiée.',
            ], 422);
        }

        $alwaysEditableFields = ['name', 'description', 'scheduled_at', 'max_players'];
        $validated = collect($request->validated())->except(['cover_image', 'remove_cover_image']);

        if (! in_array($session->status, [SessionStatus::Draft, SessionStatus::RegistrationOpen])) {
            $restrictedFields = $validated->keys()->diff($alwaysEditableFields);

            if ($restrictedFields->isNotEmpty()) {
                return response()->json([
                    'message' => 'Seuls le nom, la description, la date et le nombre de joueurs peuvent être modifiés dans ce statut.',
                ], 422);
            }
        }

        $data = $validated->toArray();

        if ($request->hasFile('cover_image')) {
            if ($session->cover_image_url) {
                Storage::disk('public')->delete($session->cover_image_url);
            }
            $data['cover_image_url'] = $request->file('cover_image')->store('media/sessions', 'public');
        } elseif ($request->boolean('remove_cover_image')) {
            if ($session->cover_image_url) {
                Storage::disk('public')->delete($session->cover_image_url);
            }
            $data['cover_image_url'] = null;
        }

        $session->update($data);

        return response()->json(new SessionResource($session));
    }

    #[OA\Delete(
        path: '/admin/sessions/{session}',
        summary: 'Supprimer une session',
        description: 'Supprime une session (statut Draft uniquement).',
        security: [['sanctum' => []]],
        tags: ['Sessions'],
        parameters: [
            new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Session supprimée'),
            new OA\Response(response: 422, description: 'Statut invalide'),
        ],
    )]
    public function destroy(Session $session): JsonResponse
    {
        if ($session->status !== SessionStatus::Draft) {
            return response()->json([
                'message' => 'Seules les sessions en brouillon peuvent être supprimées.',
            ], 422);
        }

        if ($session->cover_image_url) {
            Storage::disk('public')->delete($session->cover_image_url);
        }

        $session->delete();

        return response()->json(null, 204);
    }

    #[OA\Post(
        path: '/admin/sessions/{session}/duplicate',
        summary: 'Dupliquer une session',
        description: 'Duplique une session avec ses manches, questions (choix, indices, seconde chance) et questions de pré-sélection. Les joueurs, inscriptions et données de jeu ne sont pas copiés.',
        security: [['sanctum' => []]],
        tags: ['Sessions'],
        parameters: [
            new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 201, description: 'Session dupliquée'),
            new OA\Response(response: 404, description: 'Session introuvable'),
        ],
    )]
    public function duplicate(Request $request, Session $session): JsonResponse
    {
        $session->load([
            'rounds.questions.choices',
            'rounds.questions.hint',
            'rounds.questions.secondChanceQuestion.choices',
            'preselectionQuestions.choices',
        ]);

        // 1. Dupliquer la session
        $newSession = Session::create([
            'admin_id' => $request->user()->id,
            'name' => $session->name . ' (copie)',
            'description' => $session->description,
            'cover_image_url' => $session->cover_image_url,
            'scheduled_at' => $session->scheduled_at,
            'max_players' => $session->max_players,
            'reconnection_delay' => $session->reconnection_delay,
            'status' => SessionStatus::Draft,
        ]);

        // 2. Dupliquer les manches et leurs questions
        foreach ($session->rounds as $round) {
            $newRound = $newSession->rounds()->create([
                'round_number' => $round->round_number,
                'round_type' => $round->round_type,
                'name' => $round->name,
                'is_active' => $round->is_active,
                'status' => RoundStatus::Pending,
                'display_order' => $round->display_order,
                'rules_description' => $round->rules_description,
            ]);

            foreach ($round->questions as $question) {
                $newQuestion = $newRound->questions()->create([
                    'text' => $question->text,
                    'media_type' => $question->media_type,
                    'media_url' => $question->media_url,
                    'answer_type' => $question->answer_type,
                    'correct_answer' => $question->correct_answer,
                    'number_is_decimal' => $question->number_is_decimal,
                    'duration' => $question->duration,
                    'display_order' => $question->display_order,
                ]);

                // Choix
                foreach ($question->choices as $choice) {
                    $newQuestion->choices()->create([
                        'label' => $choice->label,
                        'is_correct' => $choice->is_correct,
                        'display_order' => $choice->display_order,
                    ]);
                }

                // Indice
                if ($question->hint) {
                    $hint = $question->hint;
                    $newQuestion->hint()->create([
                        'hint_type' => $hint->hint_type,
                        'time_penalty_seconds' => $hint->time_penalty_seconds,
                        'removed_choice_ids' => $hint->removed_choice_ids,
                        'revealed_letters' => $hint->revealed_letters,
                        'range_hint_text' => $hint->range_hint_text,
                        'range_min' => $hint->range_min,
                        'range_max' => $hint->range_max,
                    ]);
                }

                // Seconde chance
                if ($question->secondChanceQuestion) {
                    $sc = $question->secondChanceQuestion;
                    $newSc = $newQuestion->secondChanceQuestion()->create([
                        'text' => $sc->text,
                        'media_type' => $sc->media_type,
                        'media_url' => $sc->media_url,
                        'answer_type' => $sc->answer_type,
                        'correct_answer' => $sc->correct_answer,
                        'number_is_decimal' => $sc->number_is_decimal,
                        'duration' => $sc->duration,
                    ]);

                    foreach ($sc->choices as $scChoice) {
                        $newSc->choices()->create([
                            'label' => $scChoice->label,
                            'is_correct' => $scChoice->is_correct,
                            'display_order' => $scChoice->display_order,
                        ]);
                    }
                }
            }
        }

        // 3. Dupliquer les questions de pré-sélection
        foreach ($session->preselectionQuestions as $pq) {
            $newPq = $newSession->preselectionQuestions()->create([
                'text' => $pq->text,
                'media_type' => $pq->media_type,
                'media_url' => $pq->media_url,
                'answer_type' => $pq->answer_type,
                'correct_answer' => $pq->correct_answer,
                'number_is_decimal' => $pq->number_is_decimal,
                'duration' => $pq->duration,
                'display_order' => $pq->display_order,
            ]);

            foreach ($pq->choices as $pqChoice) {
                $newPq->choices()->create([
                    'label' => $pqChoice->label,
                    'is_correct' => $pqChoice->is_correct,
                    'display_order' => $pqChoice->display_order,
                ]);
            }
        }

        return response()->json(
            new SessionResource($newSession->load('rounds')),
            201
        );
    }

    /**
     * Crée les 8 manches par défaut pour une nouvelle session.
     *
     * @param  array<int, array{number: int, type: RoundType, name: string, active: bool}>  $roundDefinitions
     */
    private function createDefaultRounds(Session $session): void
    {
        $roundDefinitions = [
            ['number' => 1, 'type' => RoundType::SuddenDeath, 'name' => 'Mort subite', 'active' => true],
            ['number' => 2, 'type' => RoundType::Hint, 'name' => 'Utilisation d\'indice', 'active' => false],
            ['number' => 3, 'type' => RoundType::SecondChance, 'name' => 'Seconde chance', 'active' => false],
            ['number' => 4, 'type' => RoundType::RoundSkip, 'name' => 'Passage de manche', 'active' => false],
            ['number' => 5, 'type' => RoundType::Top4Elimination, 'name' => 'Élimination top 4', 'active' => true],
            ['number' => 6, 'type' => RoundType::DuelJackpot, 'name' => 'Duel — Tour de rôle', 'active' => true],
            ['number' => 7, 'type' => RoundType::DuelElimination, 'name' => 'Duel — Élimination', 'active' => true],
            ['number' => 8, 'type' => RoundType::Finale, 'name' => 'Finale', 'active' => true],
        ];

        foreach ($roundDefinitions as $index => $definition) {
            SessionRound::create([
                'session_id' => $session->id,
                'round_number' => $definition['number'],
                'round_type' => $definition['type'],
                'name' => $definition['name'],
                'is_active' => $definition['active'],
                'status' => RoundStatus::Pending,
                'display_order' => $index + 1,
            ]);
        }
    }
}
