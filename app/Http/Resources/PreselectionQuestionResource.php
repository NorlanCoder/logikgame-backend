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
            'duration' => $this->duration,
            'display_order' => $this->display_order,
            'media_url' => $this->media_url,
            'media_type' => $this->media_type,
            'choices' => $this->whenLoaded('choices', fn () => $this->choices->map(fn ($c) => [
                'id' => $c->id,
                'label' => $c->label,
                'display_order' => $c->display_order,
            ])),
        ];
    }
}
