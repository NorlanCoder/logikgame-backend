<?php

namespace App\Http\Requests\Player;

use Illuminate\Foundation\Http\FormRequest;

class SubmitAnswerRequest extends FormRequest
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
            'question_id' => ['required', 'integer', 'exists:questions,id'],
            'answer_value' => ['nullable', 'string', 'max:500'],
            'selected_choice_id' => ['nullable', 'integer', 'exists:question_choices,id'],
            'response_time_ms' => ['nullable', 'integer', 'min:0'],
            'is_second_chance' => ['nullable', 'boolean'],
            'second_chance_question_id' => ['nullable', 'integer', 'exists:second_chance_questions,id'],
            'selected_sc_choice_id' => ['nullable', 'integer', 'exists:second_chance_question_choices,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'question_id.required' => 'L\'identifiant de la question est obligatoire.',
            'question_id.exists' => 'Cette question n\'existe pas.',
        ];
    }
}
