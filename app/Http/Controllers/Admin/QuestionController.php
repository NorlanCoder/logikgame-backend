<?php

namespace App\Http\Controllers\Admin;

use App\Enums\QuestionStatus;
use App\Enums\RoundType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreQuestionRequest;
use App\Http\Requests\Admin\UpdateQuestionRequest;
use App\Http\Resources\QuestionResource;
use App\Models\Question;
use App\Models\QuestionChoice;
use App\Models\QuestionHint;
use App\Models\Session;
use App\Models\SessionRound;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class QuestionController extends Controller
{
    #[OA\Get(
        path: '/admin/sessions/{session}/rounds/{round}/questions',
        summary: 'Lister les questions',
        description: 'Retourne les questions d\'une manche.',
        security: [['sanctum' => []]],
        tags: ['Questions'],
        parameters: [
            new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'round', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des questions'),
        ],
    )]
    public function index(Session $session, SessionRound $round): AnonymousResourceCollection
    {
        $questions = $round->questions()
            ->withCount('choices')
            ->get();

        return QuestionResource::collection($questions);
    }

    #[OA\Post(
        path: '/admin/sessions/{session}/rounds/{round}/questions',
        summary: 'Créer une question',
        description: 'Crée une question avec choix QCM et indice optionnel.',
        security: [['sanctum' => []]],
        tags: ['Questions'],
        parameters: [
            new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'round', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['text', 'answer_type', 'correct_answer', 'duration'],
                properties: [
                    new OA\Property(property: 'text', type: 'string'),
                    new OA\Property(property: 'answer_type', type: 'string', enum: ['qcm', 'text', 'number']),
                    new OA\Property(property: 'correct_answer', type: 'string'),
                    new OA\Property(property: 'duration', type: 'integer', example: 30),
                    new OA\Property(property: 'media_url', type: 'string', nullable: true),
                    new OA\Property(property: 'media_type', type: 'string', enum: ['none', 'image', 'video', 'audio']),
                    new OA\Property(property: 'choices', type: 'array', items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'label', type: 'string'),
                            new OA\Property(property: 'is_correct', type: 'boolean'),
                            new OA\Property(property: 'display_order', type: 'integer'),
                        ],
                    )),
                    new OA\Property(property: 'hint', type: 'object', nullable: true, properties: [
                        new OA\Property(property: 'hint_type', type: 'string'),
                        new OA\Property(property: 'hint_data', type: 'string'),
                    ]),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Question créée'),
            new OA\Response(response: 422, description: 'Validation échouée'),
        ],
    )]
    public function store(StoreQuestionRequest $request, Session $session, SessionRound $round): JsonResponse
    {
        $nextOrder = $round->questions()->max('display_order') + 1;

        $question = Question::create([
            'session_round_id' => $round->id,
            'text' => $request->text,
            'answer_type' => $request->answer_type,
            'correct_answer' => $request->correct_answer,
            'duration' => $request->duration,
            'display_order' => $request->input('display_order', $nextOrder),
            'media_url' => $request->media_url,
            'media_type' => $request->input('media_type', 'none'),
            'number_is_decimal' => $request->boolean('number_is_decimal', false),
            'status' => QuestionStatus::Pending,
        ]);

        // Créer les choix QCM
        if ($request->has('choices')) {
            foreach ($request->choices as $index => $choiceData) {
                QuestionChoice::create([
                    'question_id' => $question->id,
                    'label' => $choiceData['label'],
                    'is_correct' => $choiceData['is_correct'] ?? false,
                    'display_order' => $choiceData['display_order'] ?? ($index + 1),
                ]);
            }
        }

        // Créer l'indice (manche 2 uniquement)
        if ($request->has('hint') && $round->round_type === RoundType::Hint) {
            QuestionHint::create([
                'question_id' => $question->id,
                ...$request->hint,
            ]);
        }

        return response()->json(
            new QuestionResource($question->load(['choices', 'hint'])),
            201
        );
    }

    #[OA\Get(
        path: '/admin/sessions/{session}/rounds/{round}/questions/{question}',
        summary: 'Détail d\'une question',
        description: 'Retourne le détail complet d\'une question avec choix et indice.',
        security: [['sanctum' => []]],
        tags: ['Questions'],
        parameters: [
            new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'round', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'question', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Détail question'),
        ],
    )]
    public function show(Session $session, SessionRound $round, Question $question): QuestionResource
    {
        $question->load(['choices', 'hint']);

        return new QuestionResource($question);
    }

    #[OA\Put(
        path: '/admin/sessions/{session}/rounds/{round}/questions/{question}',
        summary: 'Modifier une question',
        description: 'Met à jour une question (statut Pending uniquement).',
        security: [['sanctum' => []]],
        tags: ['Questions'],
        parameters: [
            new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'round', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'question', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Question modifiée'),
            new OA\Response(response: 422, description: 'Statut invalide'),
        ],
    )]
    public function update(UpdateQuestionRequest $request, Session $session, SessionRound $round, Question $question): JsonResponse
    {
        if ($question->status !== QuestionStatus::Pending) {
            return response()->json([
                'message' => 'Une question lancée ou clôturée ne peut plus être modifiée.',
            ], 422);
        }

        $question->update($request->validated());

        return response()->json(new QuestionResource($question->load(['choices', 'hint'])));
    }

    #[OA\Delete(
        path: '/admin/sessions/{session}/rounds/{round}/questions/{question}',
        summary: 'Supprimer une question',
        description: 'Supprime une question (statut Pending uniquement).',
        security: [['sanctum' => []]],
        tags: ['Questions'],
        parameters: [
            new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'round', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'question', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Question supprimée'),
            new OA\Response(response: 422, description: 'Statut invalide'),
        ],
    )]
    public function destroy(Session $session, SessionRound $round, Question $question): JsonResponse
    {
        if ($question->status !== QuestionStatus::Pending) {
            return response()->json([
                'message' => 'Une question lancée ou clôturée ne peut pas être supprimée.',
            ], 422);
        }

        $question->delete();

        return response()->json(null, 204);
    }
}
