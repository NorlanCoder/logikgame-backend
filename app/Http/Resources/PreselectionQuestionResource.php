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
            'question_text' => $this->question_text,
            'answer_type' => $this->answer_type,
            'time_limit_seconds' => $this->time_limit_seconds,
            'display_order' => $this->display_order,
            'media_url' => $this->media_url,
            'media_type' => $this->media_type,
            'choices' => $this->whenLoaded('choices', fn () => $this->choices->map(fn ($c) => [
                'id' => $c->id,
                'choice_text' => $c->choice_text,
            ])),
        ];
    }
}
