<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MediaType;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
    public function index(Session $session, SessionRound $round): JsonResponse
    {
        $questions = $round->questions()
            ->with(['choices', 'hint', 'secondChanceQuestion.choices'])
            ->get();

        return response()->json([
            'round' => [
                'id' => $round->id,
                'round_number' => $round->round_number,
                'round_type' => $round->round_type,
                'name' => $round->name,
                'status' => $round->status,
                'is_active' => $round->is_active,
            ],
            'data' => QuestionResource::collection($questions),
        ]);
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
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['text', 'answer_type'],
                    properties: [
                        new OA\Property(property: 'text', type: 'string', maxLength: 2000),
                        new OA\Property(property: 'answer_type', type: 'string', enum: ['qcm', 'text', 'number']),
                        new OA\Property(property: 'correct_answer', type: 'string', description: 'Obligatoire pour text/number. Pour qcm, auto-dérivé du choix is_correct=1.', nullable: true),
                        new OA\Property(property: 'duration', type: 'integer', example: 30, description: 'Durée en secondes (défaut: 30)'),
                        new OA\Property(property: 'display_order', type: 'integer', nullable: true),
                        new OA\Property(property: 'number_is_decimal', type: 'boolean', nullable: true, description: 'Uniquement pour answer_type=number'),
                        new OA\Property(property: 'media_file', type: 'string', format: 'binary', nullable: true, description: 'Fichier média (image/vidéo/audio, max 50Mo)'),
                        new OA\Property(property: 'media_type', type: 'string', enum: ['none', 'image', 'video', 'audio'], description: 'Auto-détecté si non fourni'),
                        new OA\Property(property: 'choices', type: 'array', description: 'Obligatoire si answer_type=qcm (2 à 6 choix)', items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'label', type: 'string'),
                                new OA\Property(property: 'is_correct', type: 'boolean'),
                                new OA\Property(property: 'display_order', type: 'integer', nullable: true),
                            ],
                        )),
                        new OA\Property(property: 'hint', type: 'object', nullable: true, description: 'Indice — uniquement pour les manches de type hint (manche 2)', properties: [
                            new OA\Property(property: 'hint_type', type: 'string', enum: ['remove_choices', 'reveal_letters', 'reduce_range'], description: 'Type d\'indice selon le type de question'),
                            new OA\Property(property: 'time_penalty_seconds', type: 'integer', nullable: true, description: 'Secondes retirées du timer quand l\'indice est activé'),
                            new OA\Property(property: 'removed_choice_ids', type: 'array', nullable: true, description: 'IDs des choix à retirer (hint_type=remove_choices)', items: new OA\Items(type: 'integer')),
                            new OA\Property(property: 'revealed_letters', type: 'array', nullable: true, description: 'Lettres révélées (hint_type=reveal_letters)', items: new OA\Items(type: 'string')),
                            new OA\Property(property: 'range_hint_text', type: 'string', nullable: true, description: 'Texte de l\'indice de plage (hint_type=range), ex: "Entre 50 et 100"'),
                            new OA\Property(property: 'range_min', type: 'number', nullable: true),
                            new OA\Property(property: 'range_max', type: 'number', nullable: true),
                        ]),
                    ],
                ),
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

        $mediaUrl = null;
        $mediaType = MediaType::None;

        if ($request->hasFile('media_file')) {
            $file = $request->file('media_file');
            $mediaUrl = $file->store('media/questions', 'public');
            $mediaType = $request->enum('media_type', MediaType::class) ?? $this->detectMediaType($file);
        }

        // Pour QCM, dériver la bonne réponse depuis le choix marqué is_correct
        $correctAnswer = $request->answer_type === 'qcm'
            ? collect($request->choices ?? [])->firstWhere('is_correct', true)['label'] ?? null
            : $request->correct_answer;

        $question = Question::create([
            'session_round_id' => $round->id,
            'text' => $request->text,
            'answer_type' => $request->answer_type,
            'correct_answer' => $correctAnswer,
            'duration' => $request->input('duration', 30),
            'display_order' => $request->input('display_order', $nextOrder),
            'media_url' => $mediaUrl,
            'media_type' => $mediaType,
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
        description: 'Met à jour une question (statut Pending uniquement). Envoyer via POST avec le champ `_method=PUT` pour supporter les fichiers (multipart/form-data).',
        security: [['sanctum' => []]],
        tags: ['Questions'],
        parameters: [
            new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'round', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'question', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: '_method', type: 'string', example: 'PUT', description: 'Workaround navigateur pour PUT multipart'),
                        new OA\Property(property: 'text', type: 'string', maxLength: 2000, nullable: true),
                        new OA\Property(property: 'answer_type', type: 'string', enum: ['qcm', 'text', 'number'], nullable: true),
                        new OA\Property(property: 'correct_answer', type: 'string', nullable: true),
                        new OA\Property(property: 'duration', type: 'integer', nullable: true, description: 'Durée en secondes'),
                        new OA\Property(property: 'display_order', type: 'integer', nullable: true),
                        new OA\Property(property: 'number_is_decimal', type: 'boolean', nullable: true),
                        new OA\Property(property: 'media_file', type: 'string', format: 'binary', nullable: true, description: 'Remplace le fichier existant (max 50Mo)'),
                        new OA\Property(property: 'media_type', type: 'string', enum: ['none', 'image', 'video', 'audio'], nullable: true),
                        new OA\Property(property: 'remove_media', type: 'boolean', description: 'Supprimer le média existant sans en uploader un nouveau'),
                        new OA\Property(property: 'choices', type: 'array', nullable: true, items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'label', type: 'string'),
                                new OA\Property(property: 'is_correct', type: 'boolean'),
                                new OA\Property(property: 'display_order', type: 'integer', nullable: true),
                            ],
                        )),
                        new OA\Property(property: 'hint', type: 'object', nullable: true, properties: [
                            new OA\Property(property: 'hint_type', type: 'string', enum: ['remove_choices', 'reveal_letters', 'reduce_range']),
                            new OA\Property(property: 'time_penalty_seconds', type: 'integer', nullable: true),
                            new OA\Property(property: 'removed_choice_ids', type: 'array', nullable: true, items: new OA\Items(type: 'integer')),
                            new OA\Property(property: 'revealed_letters', type: 'array', nullable: true, items: new OA\Items(type: 'string')),
                            new OA\Property(property: 'range_hint_text', type: 'string', nullable: true),
                            new OA\Property(property: 'range_min', type: 'number', nullable: true),
                            new OA\Property(property: 'range_max', type: 'number', nullable: true),
                        ]),
                    ],
                ),
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Question modifiée'),
            new OA\Response(response: 422, description: 'Statut invalide ou validation échouée'),
        ],
    )]
    public function update(UpdateQuestionRequest $request, Session $session, SessionRound $round, Question $question): JsonResponse
    {
        if ($question->status !== QuestionStatus::Pending) {
            return response()->json([
                'message' => 'Une question lancée ou clôturée ne peut plus être modifiée.',
            ], 422);
        }

        $data = collect($request->validated())->except(['media_file', 'remove_media', 'choices'])->toArray();

        if ($request->hasFile('media_file')) {
            if ($question->media_url) {
                Storage::disk('public')->delete($question->media_url);
            }
            $file = $request->file('media_file');
            $data['media_url'] = $file->store('media/questions', 'public');
            $data['media_type'] = $request->enum('media_type', MediaType::class) ?? $this->detectMediaType($file);
        } elseif ($request->boolean('remove_media')) {
            if ($question->media_url) {
                Storage::disk('public')->delete($question->media_url);
            }
            $data['media_url'] = null;
            $data['media_type'] = MediaType::None;
        }

        // Recréer les choix QCM et recalculer correct_answer
        $answerType = $data['answer_type'] ?? $question->answer_type->value;

        if ($request->has('choices') && $answerType === 'qcm') {
            $question->choices()->delete();

            foreach ($request->choices as $index => $choiceData) {
                QuestionChoice::create([
                    'question_id' => $question->id,
                    'label' => $choiceData['label'],
                    'is_correct' => $choiceData['is_correct'] ?? false,
                    'display_order' => $choiceData['display_order'] ?? ($index + 1),
                ]);
            }

            $data['correct_answer'] = collect($request->choices)->firstWhere('is_correct', true)['label'] ?? null;
        }

        $question->update($data);

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

        if ($question->media_url) {
            Storage::disk('public')->delete($question->media_url);
        }

        $question->delete();

        return response()->json(null, 204);
    }

    private function detectMediaType(UploadedFile $file): MediaType
    {
        $extension = strtolower($file->getClientOriginalExtension());

        return match (true) {
            in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']) => MediaType::Image,
            in_array($extension, ['mp4', 'webm', 'mov']) => MediaType::Video,
            in_array($extension, ['mp3', 'wav', 'ogg', 'aac']) => MediaType::Audio,
            default => MediaType::None,
        };
    }
}
