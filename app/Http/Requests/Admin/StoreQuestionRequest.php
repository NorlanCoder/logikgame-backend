<?php

namespace App\Http\Requests\Admin;

use App\Enums\AnswerType;
use App\Enums\MediaType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'max:2000'],
            'answer_type' => ['required', Rule::enum(AnswerType::class)],
            'correct_answer' => ['required', 'string', 'max:500'],
            'duration' => ['required', 'integer', 'min:5', 'max:300'],
            'display_order' => ['nullable', 'integer', 'min:1'],
            'media_url' => ['nullable', 'url', 'max:500'],
            'media_type' => ['nullable', Rule::enum(MediaType::class)],
            'number_is_decimal' => ['nullable', 'boolean'],
            // Choix QCM
            'choices' => ['nullable', 'array', 'min:2', 'max:6'],
            'choices.*.label' => ['required_with:choices', 'string', 'max:500'],
            'choices.*.is_correct' => ['required_with:choices', 'boolean'],
            'choices.*.display_order' => ['nullable', 'integer'],
            // Indice (manche 2)
            'hint' => ['nullable', 'array'],
            'hint.hint_type' => ['required_with:hint', Rule::enum(\App\Enums\HintType::class)],
            'hint.time_penalty_seconds' => ['nullable', 'integer', 'min:0', 'max:120'],
            'hint.removed_choice_ids' => ['nullable', 'array'],
            'hint.removed_choice_ids.*' => ['integer'],
            'hint.revealed_letters' => ['nullable', 'array'],
            'hint.range_hint_text' => ['nullable', 'string', 'max:500'],
            'hint.range_min' => ['nullable', 'numeric'],
            'hint.range_max' => ['nullable', 'numeric'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'text.required' => 'Le texte de la question est obligatoire.',
            'answer_type.required' => 'Le type de réponse est obligatoire.',
            'correct_answer.required' => 'La réponse correcte est obligatoire.',
            'duration.required' => 'La durée est obligatoire.',
            'choices.min' => 'Un QCM nécessite au minimum 2 choix.',
            'choices.max' => 'Un QCM ne peut pas avoir plus de 6 choix.',
        ];
    }
}
