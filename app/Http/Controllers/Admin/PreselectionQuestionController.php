<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MediaType;
use App\Http\Controllers\Controller;
use App\Http\Resources\PreselectionQuestionResource;
use App\Models\PreselectionQuestion;
use App\Models\PreselectionQuestionChoice;
use App\Models\Session;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
                        new OA\Property(property: 'choices', type: 'array', description: 'Obligatoire si answer_type=qcm (4 à 6 choix)', items: new OA\Items(
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
            new OA\Response(response: 201, description: 'Question créée'),
            new OA\Response(response: 422, description: 'Validation échouée'),
        ],
    )]
    public function store(Request $request, Session $session): JsonResponse
    {
        $validated = $request->validate([
            'text' => ['required', 'string'],
            'answer_type' => ['required', 'string', 'in:qcm,number,text'],
            'correct_answer' => ['required_unless:answer_type,qcm', 'nullable', 'string', 'max:500'],
            'duration' => ['sometimes', 'integer', 'min:5', 'max:300'],
            'display_order' => ['sometimes', 'integer', 'min:1'],
            'media_file' => ['nullable', 'file', 'max:51200', 'mimes:jpg,jpeg,png,gif,webp,mp4,webm,mov,mp3,wav,ogg,aac'],
            'media_type' => ['sometimes', 'string', 'in:none,image,video,audio'],
            'number_is_decimal' => ['sometimes', 'boolean'],
            'choices' => ['required_if:answer_type,qcm', 'array', 'min:4', 'max:6'],
            'choices.*.label' => ['required_with:choices', 'string', 'max:500'],
            'choices.*.is_correct' => ['sometimes', 'boolean'],
            'choices.*.display_order' => ['sometimes', 'integer'],
        ]);

        $nextOrder = $session->preselectionQuestions()->max('display_order') + 1;

        $mediaUrl = null;
        $mediaType = 'none';

        if ($request->hasFile('media_file')) {
            $file = $request->file('media_file');
            $mediaUrl = $file->store('media/preselection', 'public');
            $mediaType = $validated['media_type'] ?? $this->detectMediaType($file)->value;
        }

        // Pour QCM, dériver la bonne réponse depuis le choix marqué is_correct
        $correctAnswer = $validated['answer_type'] === 'qcm'
            ? collect($validated['choices'] ?? [])->firstWhere('is_correct', true)['label'] ?? null
            : $validated['correct_answer'];

        $question = PreselectionQuestion::create([
            'session_id' => $session->id,
            'text' => $validated['text'],
            'answer_type' => $validated['answer_type'],
            'correct_answer' => $correctAnswer,
            'duration' => $validated['duration'] ?? 30,
            'display_order' => $validated['display_order'] ?? $nextOrder,
            'media_type' => $mediaType,
            'media_url' => $mediaUrl,
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
        description: 'Envoyer via POST avec le champ `_method=PUT` pour supporter les fichiers (multipart/form-data).',
        security: [['sanctum' => []]],
        tags: ['Preselection Questions'],
        parameters: [
            new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'preselectionQuestion', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
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
                    ],
                ),
            ),
        ),
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
            'media_file' => ['nullable', 'file', 'max:51200', 'mimes:jpg,jpeg,png,gif,webp,mp4,webm,mov,mp3,wav,ogg,aac'],
            'media_type' => ['sometimes', 'string', 'in:none,image,video,audio'],
            'number_is_decimal' => ['sometimes', 'boolean'],
            'remove_media' => ['nullable', 'boolean'],
            'choices' => ['sometimes', 'array', 'min:4', 'max:6'],
            'choices.*.label' => ['required_with:choices', 'string', 'max:500'],
            'choices.*.is_correct' => ['sometimes', 'boolean'],
            'choices.*.display_order' => ['sometimes', 'integer'],
        ]);

        $data = collect($validated)->except(['choices', 'media_file', 'remove_media'])->toArray();

        if ($request->hasFile('media_file')) {
            if ($preselectionQuestion->media_url) {
                Storage::disk('public')->delete($preselectionQuestion->media_url);
            }
            $file = $request->file('media_file');
            $data['media_url'] = $file->store('media/preselection', 'public');
            $data['media_type'] = $validated['media_type'] ?? $this->detectMediaType($file)->value;
        } elseif (filter_var($validated['remove_media'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            if ($preselectionQuestion->media_url) {
                Storage::disk('public')->delete($preselectionQuestion->media_url);
            }
            $data['media_url'] = null;
            $data['media_type'] = 'none';
        }

        $preselectionQuestion->update($data);

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
        if ($preselectionQuestion->media_url) {
            Storage::disk('public')->delete($preselectionQuestion->media_url);
        }

        $preselectionQuestion->delete();

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
