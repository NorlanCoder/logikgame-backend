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
            'projection_code' => strtoupper(substr(md5(uniqid()), 0, 8)),
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
        if (! in_array($session->status, [SessionStatus::Draft, SessionStatus::RegistrationOpen])) {
            return response()->json([
                'message' => 'La session ne peut être modifiée que si elle est en brouillon ou en inscription ouverte.',
            ], 422);
        }

        $data = collect($request->validated())->except(['cover_image', 'remove_cover_image'])->toArray();

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
