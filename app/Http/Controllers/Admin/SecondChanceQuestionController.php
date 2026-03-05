<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AnswerType;
use App\Enums\MediaType;
use App\Enums\RoundType;
use App\Http\Controllers\Controller;
use App\Http\Resources\SecondChanceQuestionResource;
use App\Models\Question;
use App\Models\SecondChanceQuestion;
use App\Models\SecondChanceQuestionChoice;
use App\Models\Session;
use App\Models\SessionRound;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class SecondChanceQuestionController extends Controller
{
    #[OA\Get(
        path: '/admin/sessions/{session}/rounds/{round}/questions/{question}/second-chance',
        summary: 'Récupérer la question de seconde chance',
        description: 'Retourne la question de seconde chance associée à une question de la manche 3.',
        security: [['sanctum' => []]],
        tags: ['Second Chance Questions'],
        parameters: [
            new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'round', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'question', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Question de seconde chance'),
            new OA\Response(response: 404, description: 'Aucune question de seconde chance configurée'),
        ],
    )]
    public function show(Session $session, SessionRound $round, Question $question): JsonResponse
    {
        $scQuestion = $question->secondChanceQuestion;

        if (! $scQuestion) {
            return response()->json(['message' => 'Aucune question de seconde chance configurée.'], 404);
        }

        return response()->json(new SecondChanceQuestionResource($scQuestion->load('choices')));
    }

    #[OA\Put(
        path: '/admin/sessions/{session}/rounds/{round}/questions/{question}/second-chance',
        summary: 'Créer ou remplacer la question de seconde chance',
        description: 'Uniquement pour les questions de la manche de type "second_chance" (manche 3). Envoyer via POST + `_method=PUT` pour supporter les fichiers.',
        security: [['sanctum' => []]],
        tags: ['Second Chance Questions'],
        parameters: [
            new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'round', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'question', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['text', 'answer_type'],
                    properties: [
                        new OA\Property(property: '_method', type: 'string', example: 'PUT', description: 'Workaround navigateur pour PUT multipart'),
                        new OA\Property(property: 'text', type: 'string', maxLength: 2000),
                        new OA\Property(property: 'answer_type', type: 'string', enum: ['qcm', 'text', 'number']),
                        new OA\Property(property: 'correct_answer', type: 'string', nullable: true, description: 'Obligatoire pour text/number. Pour qcm, auto-dérivé du choix is_correct=1.'),
                        new OA\Property(property: 'duration', type: 'integer', example: 30, description: 'Durée en secondes (défaut: 30)'),
                        new OA\Property(property: 'number_is_decimal', type: 'boolean', nullable: true),
                        new OA\Property(property: 'media_file', type: 'string', format: 'binary', nullable: true, description: 'Fichier média (max 50Mo)'),
                        new OA\Property(property: 'media_type', type: 'string', enum: ['none', 'image', 'video', 'audio'], description: 'Auto-détecté si non fourni'),
                        new OA\Property(property: 'remove_media', type: 'boolean', description: 'Supprimer le média existant'),
                        new OA\Property(property: 'choices', type: 'array', description: 'Obligatoire si answer_type=qcm (2 à 6 choix)', items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'label', type: 'string'),
                                new OA\Property(property: 'is_correct', type: 'boolean'),
                                new OA\Property(property: 'display_order', type: 'integer', nullable: true),
                            ],
                        )),
                    ],
                ),
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Question de seconde chance créée ou mise à jour'),
            new OA\Response(response: 422, description: 'Validation échouée ou manche incompatible'),
        ],
    )]
    public function upsert(Request $request, Session $session, SessionRound $round, Question $question): JsonResponse
    {
        if ($round->round_type !== RoundType::SecondChance) {
            return response()->json([
                'message' => 'Les questions de seconde chance ne sont disponibles que pour les manches de type "second_chance" (manche 3).',
            ], 422);
        }

        $validated = $request->validate([
            'text' => ['required', 'string', 'max:2000'],
            'answer_type' => ['required', Rule::enum(AnswerType::class)],
            'correct_answer' => ['required_unless:answer_type,qcm', 'nullable', 'string', 'max:500'],
            'duration' => ['sometimes', 'integer', 'min:5', 'max:300'],
            'number_is_decimal' => ['nullable', 'boolean'],
            'media_file' => ['nullable', 'file', 'max:51200', 'mimes:jpg,jpeg,png,gif,webp,mp4,webm,mov,mp3,wav,ogg,aac'],
            'media_type' => ['nullable', Rule::enum(MediaType::class)],
            'remove_media' => ['nullable', 'boolean'],
            'choices' => ['required_if:answer_type,qcm', 'nullable', 'array', 'min:2', 'max:6'],
            'choices.*.label' => ['required_with:choices', 'string', 'max:500'],
            'choices.*.is_correct' => ['required_with:choices', 'boolean'],
            'choices.*.display_order' => ['nullable', 'integer'],
        ]);

        // Pour QCM, dériver correct_answer depuis le choix marqué is_correct
        $correctAnswer = $validated['answer_type'] === AnswerType::Qcm->value
            ? collect($validated['choices'] ?? [])->firstWhere('is_correct', true)['label'] ?? null
            : $validated['correct_answer'];

        $existing = $question->secondChanceQuestion;

        // Gestion du fichier média
        $mediaUrl = $existing?->media_url;
        $mediaType = $existing?->media_type ?? MediaType::None;

        if ($request->hasFile('media_file')) {
            if ($mediaUrl) {
                Storage::disk('public')->delete($mediaUrl);
            }
            $file = $request->file('media_file');
            $mediaUrl = $file->store('media/second-chance', 'public');
            $mediaType = $request->enum('media_type', MediaType::class) ?? $this->detectMediaType($file);
        } elseif ($request->boolean('remove_media') && $mediaUrl) {
            Storage::disk('public')->delete($mediaUrl);
            $mediaUrl = null;
            $mediaType = MediaType::None;
        }

        $scQuestion = SecondChanceQuestion::updateOrCreate(
            ['main_question_id' => $question->id],
            [
                'text' => $validated['text'],
                'answer_type' => $validated['answer_type'],
                'correct_answer' => $correctAnswer,
                'duration' => $validated['duration'] ?? 30,
                'number_is_decimal' => $validated['number_is_decimal'] ?? false,
                'media_url' => $mediaUrl,
                'media_type' => $mediaType,
            ],
        );

        // Remplacer les choix si fournis
        if (! empty($validated['choices'])) {
            $scQuestion->choices()->delete();
            foreach ($validated['choices'] as $index => $choiceData) {
                SecondChanceQuestionChoice::create([
                    'second_chance_question_id' => $scQuestion->id,
                    'label' => $choiceData['label'],
                    'is_correct' => $choiceData['is_correct'] ?? false,
                    'display_order' => $choiceData['display_order'] ?? ($index + 1),
                ]);
            }
        }

        return response()->json(new SecondChanceQuestionResource($scQuestion->load('choices')));
    }

    #[OA\Delete(
        path: '/admin/sessions/{session}/rounds/{round}/questions/{question}/second-chance',
        summary: 'Supprimer la question de seconde chance',
        security: [['sanctum' => []]],
        tags: ['Second Chance Questions'],
        parameters: [
            new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'round', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'question', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Question supprimée'),
            new OA\Response(response: 404, description: 'Aucune question de seconde chance à supprimer'),
        ],
    )]
    public function destroy(Session $session, SessionRound $round, Question $question): JsonResponse
    {
        $scQuestion = $question->secondChanceQuestion;

        if (! $scQuestion) {
            return response()->json(['message' => 'Aucune question de seconde chance à supprimer.'], 404);
        }

        if ($scQuestion->media_url) {
            Storage::disk('public')->delete($scQuestion->media_url);
        }

        $scQuestion->delete();

        return response()->json(null, 204);
    }

    private function detectMediaType(UploadedFile $file): MediaType
    {
        $mime = $file->getMimeType() ?? '';

        return match (true) {
            str_starts_with($mime, 'image/') => MediaType::Image,
            str_starts_with($mime, 'video/') => MediaType::Video,
            str_starts_with($mime, 'audio/') => MediaType::Audio,
            default => MediaType::None,
        };
    }
}
