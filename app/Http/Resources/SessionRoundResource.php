<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SessionRoundResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'session_id' => $this->session_id,
            'round_number' => $this->round_number,
            'round_type' => $this->round_type,
            'display_order' => $this->display_order,
            'is_active' => $this->is_active,
            'status' => $this->status,
            'started_at' => $this->started_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'questions_count' => $this->whenCounted('questions'),
            'questions' => QuestionResource::collection($this->whenLoaded('questions')),
        ];
    }
}
