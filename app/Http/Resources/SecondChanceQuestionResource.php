<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SecondChanceQuestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'main_question_id' => $this->main_question_id,
            'text' => $this->text,
            'answer_type' => $this->answer_type,
            'correct_answer' => $this->correct_answer,
            'number_is_decimal' => $this->number_is_decimal,
            'duration' => $this->duration,
            'media_url' => $this->media_url ? asset('storage/'.$this->media_url) : null,
            'media_type' => $this->media_type,
            'status' => $this->status,
            'launched_at' => $this->launched_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'choices' => $this->whenLoaded('choices', fn () => $this->choices->map(fn ($c) => [
                'id' => $c->id,
                'label' => $c->label,
                'is_correct' => $c->is_correct,
                'display_order' => $c->display_order,
            ])),
        ];
    }
}
