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
            'pseudo' => $this->pseudo,
            'status' => $this->status,
            'score' => $this->score,
            'jackpot_share' => $this->jackpot_share,
            'is_connected' => $this->is_connected,
            'hint_used' => $this->hint_used,
            'eliminated_at_round' => $this->eliminated_at_round,
            'player' => new PlayerResource($this->whenLoaded('player')),
        ];
    }
}
