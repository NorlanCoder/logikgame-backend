<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isAdmin = $request->user()?->getTable() === 'admins';

        return [
            'id' => $this->id,
            'session_round_id' => $this->session_round_id,
            'text' => $this->text,
            'answer_type' => $this->answer_type,
            'correct_answer' => $this->when($isAdmin, $this->correct_answer),
            'number_is_decimal' => $this->number_is_decimal,
            'duration' => $this->duration,
            'display_order' => $this->display_order,
            'media_url' => $this->media_url,
            'media_type' => $this->media_type,
            'status' => $this->status,
            'launched_at' => $this->launched_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'choices' => $this->whenLoaded('choices', fn () => $this->choices->map(fn ($c) => [
                'id' => $c->id,
                'label' => $c->label,
                'is_correct' => $this->when($isAdmin, $c->is_correct),
                'display_order' => $c->display_order,
            ])),
            'hint' => $this->when($isAdmin && $this->relationLoaded('hint'), $this->hint),
        ];
    }
}
