<?php

namespace App\Http\Controllers\Admin;

use App\Enums\HintType;
use App\Enums\RoundType;
use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionHint;
use App\Models\Session;
use App\Models\SessionRound;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class QuestionHintController extends Controller
{
    #[OA\Get(
        path: '/admin/sessions/{session}/rounds/{round}/questions/{question}/hint',
        summary: 'Récupérer l\'indice d\'une question',
        security: [['sanctum' => []]],
        tags: ['Question Hints'],
        parameters: [
            new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'round', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'question', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Indice de la question'),
            new OA\Response(response: 404, description: 'Aucun indice configuré'),
        ],
    )]
    public function show(Session $session, SessionRound $round, Question $question): JsonResponse
    {
        $hint = $question->hint;

        if (! $hint) {
            return response()->json(['message' => 'Aucun indice configuré pour cette question.'], 404);
        }

        return response()->json($hint);
    }

    #[OA\Put(
        path: '/admin/sessions/{session}/rounds/{round}/questions/{question}/hint',
        summary: 'Créer ou remplacer l\'indice d\'une question',
        description: 'Uniquement pour les questions des manches de type "hint" (manche 2). Crée l\'indice s\'il n\'existe pas, le remplace sinon.',
        security: [['sanctum' => []]],
        tags: ['Question Hints'],
        parameters: [
            new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'round', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'question', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['hint_type'],
                properties: [
                    new OA\Property(property: 'hint_type', type: 'string', enum: ['remove_choices', 'reveal_letters', 'reduce_range'], description: 'Type d\'indice selon le type de question'),
                    new OA\Property(property: 'time_penalty_seconds', type: 'integer', nullable: true, description: 'Secondes retirées du timer quand l\'indice est activé'),
                    new OA\Property(property: 'removed_choice_ids', type: 'array', nullable: true, description: 'IDs des choix à masquer (hint_type=remove_choices)', items: new OA\Items(type: 'integer')),
                    new OA\Property(property: 'revealed_letters', type: 'array', nullable: true, description: 'Positions des lettres révélées (hint_type=reveal_letters)', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'range_hint_text', type: 'string', nullable: true, description: 'Texte d\'indice libre (hint_type=range), ex: "Entre 50 et 100"'),
                    new OA\Property(property: 'range_min', type: 'number', nullable: true),
                    new OA\Property(property: 'range_max', type: 'number', nullable: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Indice mis à jour'),
            new OA\Response(response: 422, description: 'Validation échouée ou manche incompatible'),
        ],
    )]
    public function upsert(Request $request, Session $session, SessionRound $round, Question $question): JsonResponse
    {
        if ($round->round_type !== RoundType::Hint) {
            return response()->json([
                'message' => 'Les indices ne sont disponibles que pour les manches de type "hint" (manche 2).',
            ], 422);
        }

        $validated = $request->validate([
            'hint_type' => ['required', Rule::enum(HintType::class)],
            'time_penalty_seconds' => ['nullable', 'integer', 'min:0', 'max:120'],
            'removed_choice_ids' => ['nullable', 'array'],
            'removed_choice_ids.*' => ['integer'],
            'revealed_letters' => ['nullable', 'array'],
            'revealed_letters.*' => ['string'],
            'range_hint_text' => ['nullable', 'string', 'max:500'],
            'range_min' => ['nullable', 'numeric'],
            'range_max' => ['nullable', 'numeric'],
        ]);

        $hint = QuestionHint::updateOrCreate(
            ['question_id' => $question->id],
            $validated,
        );

        return response()->json($hint);
    }

    #[OA\Delete(
        path: '/admin/sessions/{session}/rounds/{round}/questions/{question}/hint',
        summary: 'Supprimer l\'indice d\'une question',
        security: [['sanctum' => []]],
        tags: ['Question Hints'],
        parameters: [
            new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'round', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'question', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Indice supprimé'),
            new OA\Response(response: 404, description: 'Aucun indice à supprimer'),
        ],
    )]
    public function destroy(Session $session, SessionRound $round, Question $question): JsonResponse
    {
        $hint = $question->hint;

        if (! $hint) {
            return response()->json(['message' => 'Aucun indice à supprimer.'], 404);
        }

        $hint->delete();

        return response()->json(null, 204);
    }
}
