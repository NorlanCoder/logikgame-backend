<?php

namespace App\Http\Controllers\Projection;

use App\Enums\QuestionStatus;
use App\Enums\SessionPlayerStatus;
use App\Http\Controllers\Controller;
use App\Models\ProjectionAccess;
use App\Models\Session;
use App\Models\SessionPlayer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class ProjectionController extends Controller
{
    /**
     * Récupère le code d'accès projection actif pour une session.
     */
    #[OA\Get(
        path: '/admin/sessions/{session}/projection',
        summary: 'Récupérer le code d\'accès projection actif',
        security: [['sanctum' => []]],
        tags: ['Projection'],
        parameters: [new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Code de projection actif'),
            new OA\Response(response: 404, description: 'Aucun code actif'),
        ],
    )]
    public function show(Session $session): JsonResponse
    {
        $projection = ProjectionAccess::query()
            ->where('session_id', $session->id)
            ->where('is_active', true)
            ->latest()
            ->first();

        if (! $projection) {
            return response()->json(['message' => 'Aucun code de projection actif pour cette session.'], 404);
        }

        return response()->json([
            'access_code' => $projection->access_code,
            'url' => "/projection/{$projection->access_code}",
            'created_at' => $projection->created_at?->toIso8601String(),
        ]);
    }

    /**
     * Génère un code d'accès projection pour une session (admin).
     */
    #[OA\Post(
        path: '/admin/sessions/{session}/projection/generate',
        summary: 'Générer un code d\'accès projection',
        security: [['sanctum' => []]],
        tags: ['Projection'],
        parameters: [new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 201, description: 'Code de projection généré'),
        ],
    )]
    public function generateCode(Session $session): JsonResponse
    {
        $projection = ProjectionAccess::create([
            'session_id' => $session->id,
            'access_code' => strtoupper(Str::random(6)),
            'is_active' => true,
        ]);

        return response()->json([
            'access_code' => $projection->access_code,
            'url' => "/projection/{$projection->access_code}",
        ], 201);
    }

    /**
     * Authentifie l'écran de projection via code d'accès.
     */
    #[OA\Post(
        path: '/projection/authenticate',
        summary: 'Authentifier l\'écran de projection',
        tags: ['Projection'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['access_code'],
                properties: [
                    new OA\Property(property: 'access_code', type: 'string', maxLength: 6, minLength: 6),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Authentification réussie'),
            new OA\Response(response: 401, description: 'Code invalide'),
        ],
    )]
    public function authenticate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'access_code' => ['required', 'string', 'size:6'],
        ]);

        $projection = ProjectionAccess::query()
            ->where('access_code', strtoupper($validated['access_code']))
            ->where('is_active', true)
            ->with('session')
            ->first();

        if (! $projection) {
            return response()->json(['message' => 'Code d\'accès invalide ou désactivé.'], 401);
        }

        $projection->update([
            'last_sync_at' => now(),
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'session_id' => $projection->session_id,
            'session_name' => $projection->session->name,
            'access_code' => $projection->access_code,
        ]);
    }

    /**
     * Synchronise l'état complet de la session pour la projection.
     * Endpoint principal appelé régulièrement ou après reconnexion.
     */
    #[OA\Get(
        path: '/projection/{accessCode}/sync',
        summary: 'Synchroniser l\'état de la session pour la projection',
        tags: ['Projection'],
        parameters: [new OA\Parameter(name: 'accessCode', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 200, description: 'État complet de la session'),
            new OA\Response(response: 401, description: 'Code invalide'),
        ],
    )]
    public function sync(Request $request, string $accessCode): JsonResponse
    {
        $projection = $this->resolveProjection($accessCode);

        if (! $projection) {
            return response()->json(['message' => 'Code d\'accès invalide.'], 401);
        }

        $session = $projection->session;
        $session->load(['currentRound', 'currentQuestion.choices']);

        $projection->update(['last_sync_at' => now()]);

        // Joueurs actifs (pseudos uniquement)
        $activePlayers = SessionPlayer::query()
            ->where('session_id', $session->id)
            ->where('status', SessionPlayerStatus::Active)
            ->with('player:id,pseudo')
            ->get()
            ->map(fn (SessionPlayer $sp) => [
                'id' => $sp->id,
                'pseudo' => $sp->player->pseudo ?? 'Joueur',
            ]);

        // Derniers éliminés (de la question courante ou dernière)
        $recentEliminated = SessionPlayer::query()
            ->where('session_id', $session->id)
            ->where('status', SessionPlayerStatus::Eliminated)
            ->with('player:id,pseudo')
            ->orderByDesc('eliminated_at')
            ->limit(20)
            ->get()
            ->map(fn (SessionPlayer $sp) => [
                'pseudo' => $sp->player->pseudo ?? 'Joueur',
                'eliminated_at' => $sp->eliminated_at?->toIso8601String(),
                'round_id' => $sp->eliminated_in_round_id,
            ]);

        // Question courante (sans les réponses des joueurs)
        $currentQuestion = null;
        if ($session->currentQuestion) {
            $q = $session->currentQuestion;

            $currentQuestion = [
                'id' => $q->id,
                'text' => $q->text,
                'answer_type' => $q->answer_type,
                'media_url' => $q->media_url ? asset('storage/'.$q->media_url) : null,
                'media_type' => $q->media_type,
                'duration' => $q->duration,
                'status' => $q->status,
                'launched_at' => $q->launched_at?->toIso8601String(),
                'closed_at' => $q->closed_at?->toIso8601String(),
                'choices' => $q->status === QuestionStatus::Launched
                    ? $q->choices->map(fn ($c) => [
                        'id' => $c->id,
                        'label' => $c->label,
                        'display_order' => $c->display_order,
                    ])
                    : $q->choices->map(fn ($c) => [
                        'id' => $c->id,
                        'label' => $c->label,
                        'display_order' => $c->display_order,
                        'is_correct' => $c->is_correct,
                    ]),
                // Réponse correcte seulement après reveal
                'correct_answer' => in_array($q->status, [QuestionStatus::Revealed, QuestionStatus::Closed])
                    ? $q->correct_answer
                    : null,
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
            'current_round' => $session->currentRound ? [
                'id' => $session->currentRound->id,
                'round_number' => $session->currentRound->round_number,
                'name' => $session->currentRound->name,
                'round_type' => $session->currentRound->round_type,
                'rules_description' => $session->currentRound->rules_description,
            ] : null,
            'current_question' => $currentQuestion,
            'active_players' => $activePlayers,
            'recent_eliminated' => $recentEliminated,
        ]);
    }

    /**
     * Résout la projection depuis un code d'accès.
     */
    private function resolveProjection(string $accessCode): ?ProjectionAccess
    {
        return ProjectionAccess::query()
            ->where('access_code', strtoupper($accessCode))
            ->where('is_active', true)
            ->with('session')
            ->first();
    }
}
