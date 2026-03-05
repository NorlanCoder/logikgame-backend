<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PreselectionQuestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'text' => $this->text,
            'answer_type' => $this->answer_type,
            'correct_answer' => $this->correct_answer,
            'number_is_decimal' => $this->number_is_decimal,
            'duration' => $this->duration,
            'display_order' => $this->display_order,
            'media_url' => $this->media_url ? asset('storage/'.$this->media_url) : null,
            'media_type' => $this->media_type,
            'choices' => $this->whenLoaded('choices', fn () => $this->choices->map(fn ($c) => [
                'id' => $c->id,
                'label' => $c->label,
                'is_correct' => $c->is_correct,
                'display_order' => $c->display_order,
            ])),
        ];
    }
}
