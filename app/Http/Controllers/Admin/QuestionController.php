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
use App\Models\SessionRound;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class QuestionController extends Controller
{
    public function index(SessionRound $round): AnonymousResourceCollection
    {
        $questions = $round->questions()
            ->withCount('choices')
            ->get();

        return QuestionResource::collection($questions);
    }

    public function store(StoreQuestionRequest $request, SessionRound $round): JsonResponse
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

    public function show(SessionRound $round, Question $question): QuestionResource
    {
        $question->load(['choices', 'hint']);

        return new QuestionResource($question);
    }

    public function update(UpdateQuestionRequest $request, SessionRound $round, Question $question): JsonResponse
    {
        if ($question->status !== QuestionStatus::Pending) {
            return response()->json([
                'message' => 'Une question lancée ou clôturée ne peut plus être modifiée.',
            ], 422);
        }

        $question->update($request->validated());

        return response()->json(new QuestionResource($question->load(['choices', 'hint'])));
    }

    public function destroy(SessionRound $round, Question $question): JsonResponse
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
