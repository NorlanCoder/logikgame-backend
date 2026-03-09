<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SessionRoundResource;
use App\Models\Session;
use App\Models\SessionRound;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class SessionRoundController extends Controller
{
    #[OA\Get(
        path: '/admin/sessions/{session}/rounds',
        summary: 'Lister les manches',
        description: 'Retourne les 8 manches d\'une session avec le nombre de questions.',
        security: [['sanctum' => []]],
        tags: ['Rounds'],
        parameters: [
            new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des manches'),
        ],
    )]
    public function index(Session $session): AnonymousResourceCollection
    {
        $rounds = $session->rounds()
            ->with(['questions' => fn ($q) => $q->with(['choices', 'hint', 'secondChanceQuestion.choices'])])
            ->withCount('questions')
            ->get();

        return SessionRoundResource::collection($rounds);
    }

    #[OA\Patch(
        path: '/admin/sessions/{session}/rounds/{round}',
        summary: 'Modifier une manche',
        description: 'Met à jour le nom, l\'activation ou la description d\'une manche. Les manches 5-8 ne peuvent pas être désactivées.',
        security: [['sanctum' => []]],
        tags: ['Rounds'],
        parameters: [
            new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'round', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'is_active', type: 'boolean'),
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'rules_description', type: 'string', nullable: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Manche modifiée'),
            new OA\Response(response: 422, description: 'Contrainte métier violée'),
        ],
    )]
    public function update(Request $request, Session $session, SessionRound $round): JsonResponse
    {
        $validated = $request->validate([
            'is_active' => ['sometimes', 'boolean'],
            'name' => ['sometimes', 'string', 'max:255'],
            'rules_description' => ['nullable', 'string', 'max:2000'],
        ]);

        // Faire en sorte qu'au moins 1 des manches 1-3 reste active
        if (isset($validated['is_active']) && ! $validated['is_active'] && $round->round_number <= 3) {
            $activeFirstRoundsCount = $session->rounds()
                ->where('round_number', '<=', 3)
                ->where('is_active', true)
                ->where('id', '!=', $round->id)
                ->count();

            if ($activeFirstRoundsCount === 0) {
                return response()->json([
                    'message' => 'Au moins une des manches 1 à 3 doit rester active.',
                ], 422);
            }
        }

        // Les manches 5-8 sont obligatoires
        if (isset($validated['is_active']) && ! $validated['is_active'] && $round->round_number >= 5) {
            return response()->json([
                'message' => 'Les manches 5 à 8 sont obligatoires et ne peuvent pas être désactivées.',
            ], 422);
        }

        $round->update($validated);

        return response()->json(new SessionRoundResource($round));
    }
}
