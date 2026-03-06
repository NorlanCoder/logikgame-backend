<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Models\Registration;
use App\Models\Session;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class PreselectionController extends Controller
{
    /**
     * Liste des inscrits avec leur score de pré-sélection.
     * Triés par score décroissant puis temps total croissant.
     */
    #[OA\Get(
        path: '/admin/sessions/{session}/preselection/registrations',
        summary: 'Liste des inscrits avec leurs résultats de pré-sélection',
        security: [['sanctum' => []]],
        tags: ['Admin Preselection'],
        parameters: [
            new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string'), description: 'Filtrer par statut : registered, preselection_pending, preselection_done, selected, rejected'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des inscrits triés par score'),
        ],
    )]
    public function registrations(Session $session): JsonResponse
    {
        $statusFilter = request('status');

        $registrations = Registration::query()
            ->where('session_id', $session->id)
            ->when($statusFilter, fn ($q) => $q->where('status', $statusFilter))
            ->with(['player', 'preselectionResult'])
            ->get()
            ->sortByDesc(fn ($r) => [
                $r->preselectionResult?->correct_answers_count ?? -1,
                -($r->preselectionResult?->total_response_time_ms ?? PHP_INT_MAX),
            ])
            ->values();

        $stats = [
            'total_registered' => $registrations->count(),
            'total_done' => $registrations->where('status', RegistrationStatus::PreselectionDone)->count(),
            'total_pending' => $registrations->where('status', RegistrationStatus::PreselectionPending)->count(),
            'total_not_started' => $registrations->where('status', RegistrationStatus::Registered)->count(),
            'total_selected' => $registrations->where('status', RegistrationStatus::Selected)->count(),
            'total_rejected' => $registrations->where('status', RegistrationStatus::Rejected)->count(),
        ];

        $data = $registrations->map(fn ($r) => [
            'registration_id' => $r->id,
            'status' => $r->status,
            'registered_at' => $r->registered_at,
            'player' => $r->player ? [
                'id' => $r->player->id,
                'full_name' => $r->player->full_name,
                'pseudo' => $r->player->pseudo,
                'email' => $r->player->email,
                'phone' => $r->player->phone,
            ] : null,
            'preselection_result' => $r->preselectionResult ? [
                'correct_answers_count' => $r->preselectionResult->correct_answers_count,
                'total_questions' => $r->preselectionResult->total_questions,
                'score_percent' => $r->preselectionResult->total_questions > 0
                    ? round(($r->preselectionResult->correct_answers_count / $r->preselectionResult->total_questions) * 100, 1)
                    : 0,
                'total_response_time_ms' => $r->preselectionResult->total_response_time_ms,
                'completed_at' => $r->preselectionResult->completed_at,
            ] : null,
        ]);

        return response()->json([
            'stats' => $stats,
            'data' => $data,
        ]);
    }

    /**
     * Détail d'une inscription : joueur + résultat + réponses question par question.
     */
    #[OA\Get(
        path: '/admin/sessions/{session}/preselection/registrations/{registration}',
        summary: 'Détail d\'un inscrit avec ses réponses',
        security: [['sanctum' => []]],
        tags: ['Admin Preselection'],
        parameters: [
            new OA\Parameter(name: 'session', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'registration', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Détail de l\'inscrit'),
            new OA\Response(response: 404, description: 'Inscription non trouvée'),
        ],
    )]
    public function registrationDetail(Session $session, Registration $registration): JsonResponse
    {
        if ($registration->session_id !== $session->id) {
            return response()->json(['message' => 'Inscription non trouvée pour cette session.'], 404);
        }

        $registration->load([
            'player',
            'preselectionResult',
            'preselectionAnswers.preselectionQuestion',
            'preselectionAnswers.selectedChoice',
        ]);

        $answers = $registration->preselectionAnswers->map(fn ($a) => [
            'question_id' => $a->preselection_question_id,
            'question_text' => $a->preselectionQuestion->text,
            'answer_type' => $a->preselectionQuestion->answer_type,
            'answer_value' => $a->answer_value,
            'selected_choice' => $a->selectedChoice?->label,
            'correct_answer' => $a->preselectionQuestion->correct_answer,
            'is_correct' => $a->is_correct,
            'response_time_ms' => $a->response_time_ms,
        ]);

        return response()->json([
            'registration_id' => $registration->id,
            'status' => $registration->status,
            'registered_at' => $registration->registered_at,
            'player' => [
                'id' => $registration->player->id,
                'full_name' => $registration->player->full_name,
                'pseudo' => $registration->player->pseudo,
                'email' => $registration->player->email,
                'phone' => $registration->player->phone,
            ],
            'preselection_result' => $registration->preselectionResult ? [
                'correct_answers_count' => $registration->preselectionResult->correct_answers_count,
                'total_questions' => $registration->preselectionResult->total_questions,
                'score_percent' => $registration->preselectionResult->total_questions > 0
                    ? round(($registration->preselectionResult->correct_answers_count / $registration->preselectionResult->total_questions) * 100, 1)
                    : 0,
                'total_response_time_ms' => $registration->preselectionResult->total_response_time_ms,
                'completed_at' => $registration->preselectionResult->completed_at,
            ] : null,
            'answers' => $answers,
        ]);
    }
}
