<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegistrationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'session_id' => $this->session_id,
            'player_id' => $this->player_id,
            'status' => $this->status,
            'registered_at' => $this->registered_at?->toIso8601String(),
            'player' => $this->whenLoaded('player', fn () => [
                'full_name' => $this->player->full_name,
                'email' => $this->player->email,
                'phone' => $this->player->phone,
                'pseudo' => $this->player->pseudo,
            ]),
            'preselection_result' => $this->whenLoaded('preselectionResult', fn () => [
                'correct_answers_count' => $this->preselectionResult->correct_answers_count,
                'total_questions' => $this->preselectionResult->total_questions,
                'total_response_time_ms' => $this->preselectionResult->total_response_time_ms,
                'rank' => $this->preselectionResult->rank,
                'is_selected' => $this->preselectionResult->is_selected,
            ]),
        ];
    }
}
