<?php

namespace App\Http\Controllers\Player;

use App\Enums\SessionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\SessionResource;
use App\Models\Session;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class SessionController extends Controller
{
    /**
     * Retourne les sessions publiquement accessibles :
     * - preselection → inscriptions + quiz actifs
     * - ready / in_progress → sessions en cours ou sur le point de commencer
     */
    #[OA\Get(
        path: '/player/sessions',
        summary: 'Lister les sessions publiques (inscription, prêtes ou en cours)',
        tags: ['Player Sessions'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des sessions publiques',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(type: 'object'),
                ),
            ),
        ],
    )]
    public function index(): AnonymousResourceCollection
    {
        $sessions = Session::query()
            ->whereIn('status', [
                SessionStatus::Preselection,
                SessionStatus::Ready,
                SessionStatus::InProgress,
            ])
            ->orderBy('scheduled_at')
            ->get();

        return SessionResource::collection($sessions);
    }

    /**
     * Retourne le détail public d'une session.
     * Accessible pour toute session (pas seulement preselection).
     */
    #[OA\Get(
        path: '/player/sessions/{session}',
        summary: 'Détail public d\'une session',
        tags: ['Player Sessions'],
        parameters: [new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Détail de la session'),
            new OA\Response(response: 404, description: 'Session introuvable'),
        ],
    )]
    public function show(Session $session): SessionResource
    {
        return new SessionResource($session);
    }
}
