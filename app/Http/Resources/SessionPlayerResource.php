<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SessionPlayerResource extends JsonResource
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
            'capital' => $this->capital,
            'personal_jackpot' => $this->personal_jackpot,
            'final_gain' => $this->final_gain,
            'is_connected' => $this->is_connected,
            'eliminated_at' => $this->eliminated_at?->toIso8601String(),
            'elimination_reason' => $this->elimination_reason,
            'eliminated_in_round_id' => $this->eliminated_in_round_id,
            'player' => new PlayerResource($this->whenLoaded('player')),
        ];
    }
}
