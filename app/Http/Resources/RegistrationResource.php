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
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'pseudo' => $this->pseudo,
            'status' => $this->status,
            'is_selected' => $this->is_selected,
            'preselection_score' => $this->preselection_score,
            'preselection_time_ms' => $this->preselection_time_ms,
            'registration_token' => $this->registration_token,
            'registered_at' => $this->registered_at?->toIso8601String(),
        ];
    }
}
