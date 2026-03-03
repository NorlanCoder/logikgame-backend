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

class PreselectionQuestionController extends Controller
{
    public function index(Session $session): AnonymousResourceCollection
    {
        $questions = $session->preselectionQuestions()
            ->with('choices')
            ->orderBy('display_order')
            ->get();

        return PreselectionQuestionResource::collection($questions);
    }

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

    public function show(Session $session, PreselectionQuestion $preselectionQuestion): PreselectionQuestionResource
    {
        $preselectionQuestion->load('choices');

        return new PreselectionQuestionResource($preselectionQuestion);
    }

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

    public function destroy(Session $session, PreselectionQuestion $preselectionQuestion): JsonResponse
    {
        $preselectionQuestion->delete();

        return response()->json(null, 204);
    }
}
