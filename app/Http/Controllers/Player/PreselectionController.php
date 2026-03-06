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
use OpenApi\Attributes as OA;

class PreselectionController extends Controller
{
    /**
     * Vérifie un token de pré-sélection et retourne les infos du joueur/inscription.
     */
    #[OA\Get(
        path: '/player/preselection/verify',
        summary: 'Vérifier un token de pré-sélection',
        tags: ['Player Preselection'],
        parameters: [new OA\Parameter(name: 'token', in: 'query', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 200, description: 'Informations de l\'inscription'),
            new OA\Response(response: 404, description: 'Token invalide'),
        ],
    )]
    public function verify(): JsonResponse
    {
        $token = request()->query('token');

        if (empty($token)) {
            return response()->json(['message' => 'Token manquant.'], 422);
        }

        $registration = Registration::query()
            ->where('preselection_token', $token)
            ->with(['player', 'session', 'preselectionResult'])
            ->first();

        if (! $registration) {
            return response()->json(['message' => 'Token invalide ou expiré.'], 404);
        }

        $hasCompleted = $registration->preselectionResult !== null
            || $registration->status === RegistrationStatus::PreselectionDone;

        return response()->json([
            'player' => [
                'full_name' => $registration->player->full_name,
                'pseudo' => $registration->player->pseudo,
                'email' => $registration->player->email,
            ],
            'session' => [
                'id' => $registration->session->id,
                'name' => $registration->session->name,
                'scheduled_at' => $registration->session->scheduled_at,
            ],
            'registration' => [
                'id' => $registration->id,
                'status' => $registration->status,
            ],
            'has_completed' => $hasCompleted,
        ]);
    }

    /**
     * Récupère les questions de pré-sélection pour une session.
     */
    #[OA\Get(
        path: '/player/sessions/{session}/preselection/questions',
        summary: 'Récupérer les questions de pré-sélection',
        tags: ['Player Preselection'],
        parameters: [new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Liste des questions de pré-sélection'),
        ],
    )]
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
    #[OA\Post(
        path: '/player/preselection/submit',
        summary: 'Soumettre les réponses de pré-sélection',
        tags: ['Player Preselection'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['registration_token', 'answers'],
                properties: [
                    new OA\Property(property: 'registration_token', type: 'string'),
                    new OA\Property(
                        property: 'answers',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'preselection_question_id', type: 'integer'),
                                new OA\Property(property: 'answer_value', type: 'string', nullable: true),
                                new OA\Property(property: 'selected_choice_id', type: 'integer', nullable: true),
                                new OA\Property(property: 'response_time_ms', type: 'integer', nullable: true),
                            ],
                        ),
                    ),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Réponses soumises avec score'),
            new OA\Response(response: 404, description: 'Inscription non trouvée'),
            new OA\Response(response: 422, description: 'Déjà soumis'),
        ],
    )]
    public function submit(SubmitPreselectionAnswerRequest $request): JsonResponse
    {
        $registration = Registration::query()
            ->where('preselection_token', $request->input('registration_token'))
            ->with(['session.preselectionQuestions.choices'])
            ->first();

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
            AnswerType::Qcm => isset($answerData['selected_choice_id'])
                && $question->choices->where('id', $answerData['selected_choice_id'])->where('is_correct', true)->isNotEmpty(),

            AnswerType::Text => isset($answerData['answer_value'])
                && strtolower(trim((string) $answerData['answer_value']))
                === strtolower(trim((string) $question->correct_answer)),

            AnswerType::Number => isset($answerData['answer_value'])
                && is_numeric($answerData['answer_value'])
                && abs((float) $answerData['answer_value'] - (float) $question->correct_answer) <= 0.0001,

            default => false,
        };
    }
}
