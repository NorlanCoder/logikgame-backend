<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PreselectionQuestionResource;
use App\Models\PreselectionQuestion;
use App\Models\PreselectionQuestionChoice;
use App\Models\Session;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class PreselectionQuestionController extends Controller
{
    #[OA\Get(
        path: '/admin/sessions/{session}/preselection-questions',
        summary: 'Lister les questions de pré-sélection',
        security: [['sanctum' => []]],
        tags: ['Preselection Questions'],
        parameters: [
            new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Liste des questions')],
    )]
    public function index(Session $session): AnonymousResourceCollection
    {
        $questions = $session->preselectionQuestions()
            ->with('choices')
            ->orderBy('display_order')
            ->get();

        return PreselectionQuestionResource::collection($questions);
    }

    #[OA\Post(
        path: '/admin/sessions/{session}/preselection-questions',
        summary: 'Créer une question de pré-sélection',
        security: [['sanctum' => []]],
        tags: ['Preselection Questions'],
        parameters: [
            new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['text', 'answer_type', 'correct_answer'],
                properties: [
                    new OA\Property(property: 'text', type: 'string'),
                    new OA\Property(property: 'answer_type', type: 'string', enum: ['qcm', 'text', 'number']),
                    new OA\Property(property: 'correct_answer', type: 'string'),
                    new OA\Property(property: 'choices', type: 'array', items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'label', type: 'string'),
                            new OA\Property(property: 'is_correct', type: 'boolean'),
                        ],
                    )),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Question créée'),
            new OA\Response(response: 422, description: 'Validation échouée'),
        ],
    )]
    public function store(Request $request, Session $session): JsonResponse
    {
        $validated = $request->validate([
            'text' => ['required', 'string'],
            'answer_type' => ['required', 'string', 'in:qcm,number,text'],
            'correct_answer' => ['required', 'string', 'max:500'],
            'duration' => ['sometimes', 'integer', 'min:5', 'max:300'],
            'display_order' => ['sometimes', 'integer', 'min:1'],
            'media_type' => ['sometimes', 'string', 'in:none,image,video,audio'],
            'media_url' => ['nullable', 'string', 'max:500'],
            'number_is_decimal' => ['sometimes', 'boolean'],
            'choices' => ['required_if:answer_type,qcm', 'array', 'min:4', 'max:6'],
            'choices.*.label' => ['required_with:choices', 'string', 'max:500'],
            'choices.*.is_correct' => ['sometimes', 'boolean'],
            'choices.*.display_order' => ['sometimes', 'integer'],
        ]);

        $nextOrder = $session->preselectionQuestions()->max('display_order') + 1;

        $question = PreselectionQuestion::create([
            'session_id' => $session->id,
            'text' => $validated['text'],
            'answer_type' => $validated['answer_type'],
            'correct_answer' => $validated['correct_answer'],
            'duration' => $validated['duration'] ?? 30,
            'display_order' => $validated['display_order'] ?? $nextOrder,
            'media_type' => $validated['media_type'] ?? 'none',
            'media_url' => $validated['media_url'] ?? null,
            'number_is_decimal' => $validated['number_is_decimal'] ?? false,
        ]);

        if (! empty($validated['choices'])) {
            foreach ($validated['choices'] as $index => $choiceData) {
                PreselectionQuestionChoice::create([
                    'preselection_question_id' => $question->id,
                    'label' => $choiceData['label'],
                    'is_correct' => $choiceData['is_correct'] ?? false,
                    'display_order' => $choiceData['display_order'] ?? ($index + 1),
                ]);
            }
        }

        return response()->json(
            new PreselectionQuestionResource($question->load('choices')),
            201
        );
    }

    #[OA\Get(
        path: '/admin/sessions/{session}/preselection-questions/{preselectionQuestion}',
        summary: 'Détail question pré-sélection',
        security: [['sanctum' => []]],
        tags: ['Preselection Questions'],
        parameters: [
            new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'preselectionQuestion', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'Détail question')],
    )]
    public function show(Session $session, PreselectionQuestion $preselectionQuestion): PreselectionQuestionResource
    {
        $preselectionQuestion->load('choices');

        return new PreselectionQuestionResource($preselectionQuestion);
    }

    #[OA\Put(
        path: '/admin/sessions/{session}/preselection-questions/{preselectionQuestion}',
        summary: 'Modifier question pré-sélection',
        security: [['sanctum' => []]],
        tags: ['Preselection Questions'],
        parameters: [
            new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'preselectionQuestion', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Question modifiée'),
            new OA\Response(response: 422, description: 'Validation échouée'),
        ],
    )]
    public function update(Request $request, Session $session, PreselectionQuestion $preselectionQuestion): JsonResponse
    {
        $validated = $request->validate([
            'text' => ['sometimes', 'string'],
            'answer_type' => ['sometimes', 'string', 'in:qcm,number,text'],
            'correct_answer' => ['sometimes', 'string', 'max:500'],
            'duration' => ['sometimes', 'integer', 'min:5', 'max:300'],
            'display_order' => ['sometimes', 'integer', 'min:1'],
            'media_type' => ['sometimes', 'string', 'in:none,image,video,audio'],
            'media_url' => ['nullable', 'string', 'max:500'],
            'number_is_decimal' => ['sometimes', 'boolean'],
            'choices' => ['sometimes', 'array', 'min:4', 'max:6'],
            'choices.*.label' => ['required_with:choices', 'string', 'max:500'],
            'choices.*.is_correct' => ['sometimes', 'boolean'],
            'choices.*.display_order' => ['sometimes', 'integer'],
        ]);

        $preselectionQuestion->update(collect($validated)->except('choices')->toArray());

        if (isset($validated['choices'])) {
            $preselectionQuestion->choices()->delete();

            foreach ($validated['choices'] as $index => $choiceData) {
                PreselectionQuestionChoice::create([
                    'preselection_question_id' => $preselectionQuestion->id,
                    'label' => $choiceData['label'],
                    'is_correct' => $choiceData['is_correct'] ?? false,
                    'display_order' => $choiceData['display_order'] ?? ($index + 1),
                ]);
            }
        }

        return response()->json(
            new PreselectionQuestionResource($preselectionQuestion->load('choices'))
        );
    }

    #[OA\Delete(
        path: '/admin/sessions/{session}/preselection-questions/{preselectionQuestion}',
        summary: 'Supprimer question pré-sélection',
        security: [['sanctum' => []]],
        tags: ['Preselection Questions'],
        parameters: [
            new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'preselectionQuestion', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Question supprimée'),
        ],
    )]
    public function destroy(Session $session, PreselectionQuestion $preselectionQuestion): JsonResponse
    {
        $preselectionQuestion->delete();

        return response()->json(null, 204);
    }
}
