<?php

namespace App\Http\Controllers\Player;

use App\Enums\AnswerType;
use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Player\SubmitPreselectionAnswerRequest;
use App\Http\Resources\PreselectionQuestionResource;
use App\Models\PreselectionAnswer;
use App\Models\PreselectionResult;
use App\Models\Registration;
use App\Models\Session;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class PreselectionController extends Controller
{
    /**
     * Récupère les questions de pré-sélection pour une session.
     */
    public function questions(Session $session): AnonymousResourceCollection
    {
        $questions = $session->preselectionQuestions()
            ->with('choices')
            ->get();

        return PreselectionQuestionResource::collection($questions);
    }

    /**
     * Soumet les réponses de pré-sélection et calcule le score.
     */
    public function submit(SubmitPreselectionAnswerRequest $request): JsonResponse
    {
        $registration = Registration::query()
            ->where('id', $request->input('registration_id'))
            ->with(['session.preselectionQuestions.choices'])
            ->first();

        if (! $registration) {
            // Chercher par token si fourni
            $registration = Registration::query()
                ->whereHas('sessionPlayer', fn ($q) => $q->where('access_token', $request->registration_token))
                ->orWhere(function ($q) use ($request) {
                    $q->where('id', $request->input('registration_id', 0));
                })
                ->with(['session.preselectionQuestions.choices'])
                ->first();
        }

        if (! $registration) {
            return response()->json(['message' => 'Inscription non trouvée.'], 404);
        }

        if ($registration->preselectionResult()->exists()) {
            return response()->json(['message' => 'Vous avez déjà soumis vos réponses.'], 422);
        }

        if ($registration->status === RegistrationStatus::PreselectionDone) {
            return response()->json(['message' => 'Pré-sélection déjà complétée.'], 422);
        }

        $result = DB::transaction(function () use ($request, $registration) {
            $correctCount = 0;
            $totalTimeMs = 0;
            $questions = $registration->session->preselectionQuestions->keyBy('id');

            foreach ($request->answers as $answerData) {
                $question = $questions->get($answerData['preselection_question_id']);

                if (! $question) {
                    continue;
                }

                $isCorrect = $this->checkPreselectionAnswer($question, $answerData);

                if ($isCorrect) {
                    $correctCount++;
                }

                $responseTimeMs = $answerData['response_time_ms'] ?? null;
                $totalTimeMs += $responseTimeMs ?? 0;

                PreselectionAnswer::create([
                    'registration_id' => $registration->id,
                    'preselection_question_id' => $question->id,
                    'answer_value' => $answerData['answer_value'] ?? null,
                    'selected_choice_id' => $answerData['selected_choice_id'] ?? null,
                    'is_correct' => $isCorrect,
                    'response_time_ms' => $responseTimeMs,
                    'submitted_at' => now(),
                ]);
            }

            $preselectResult = PreselectionResult::create([
                'registration_id' => $registration->id,
                'correct_answers_count' => $correctCount,
                'total_questions' => $questions->count(),
                'total_response_time_ms' => $totalTimeMs,
                'completed_at' => now(),
            ]);

            $registration->update(['status' => RegistrationStatus::PreselectionDone]);

            return $preselectResult;
        });

        return response()->json([
            'message' => 'Réponses soumises avec succès.',
            'correct_answers' => $result->correct_answers_count,
            'total_questions' => $result->total_questions,
            'total_response_time_ms' => $result->total_response_time_ms,
        ]);
    }

    private function checkPreselectionAnswer(\App\Models\PreselectionQuestion $question, array $answerData): bool
    {
        return match ($question->answer_type) {
            AnswerType::MultipleChoice => isset($answerData['selected_choice_id'])
                && $question->choices->where('id', $answerData['selected_choice_id'])->where('is_correct', true)->isNotEmpty(),

            AnswerType::FreeText => isset($answerData['answer_value'])
                && strtolower(trim((string) $answerData['answer_value']))
                === strtolower(trim((string) $question->correct_answer)),

            AnswerType::Numeric => isset($answerData['answer_value'])
                && is_numeric($answerData['answer_value'])
                && abs((float) $answerData['answer_value'] - (float) $question->correct_answer) <= 0.0001,

            default => false,
        };
    }
}
