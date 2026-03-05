<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'pseudo' => $this->pseudo,
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_url,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
