<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'cover_image_url' => $this->cover_image_url ? asset('storage/'.$this->cover_image_url) : null,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'max_players' => $this->max_players,
            'status' => $this->status,
            'registration_opens_at' => $this->registration_opens_at?->toIso8601String(),
            'registration_closes_at' => $this->registration_closes_at?->toIso8601String(),
            'preselection_opens_at' => $this->preselection_opens_at?->toIso8601String(),
            'preselection_closes_at' => $this->preselection_closes_at?->toIso8601String(),
            'jackpot' => $this->jackpot,
            'players_remaining' => $this->players_remaining,
            'reconnection_delay' => $this->reconnection_delay,
            'projection_code' => $this->projection_code,
            'started_at' => $this->started_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'rounds_count' => $this->whenCounted('rounds'),
            'registrations_count' => $this->whenCounted('registrations'),
            'rounds' => SessionRoundResource::collection($this->whenLoaded('rounds')),
            'current_round' => new SessionRoundResource($this->whenLoaded('currentRound')),
        ];
    }
}
