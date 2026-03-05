<?php

namespace App\Http\Requests\Player;

use Illuminate\Foundation\Http\FormRequest;

class SubmitPreselectionAnswerRequest extends FormRequest
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
            'registration_token' => ['required', 'string'],
            'answers' => ['required', 'array', 'min:1'],
            'answers.*.preselection_question_id' => ['required', 'integer', 'exists:preselection_questions,id'],
            'answers.*.answer_value' => ['nullable', 'string', 'max:500'],
            'answers.*.selected_choice_id' => ['nullable', 'integer', 'exists:preselection_question_choices,id'],
            'answers.*.response_time_ms' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'registration_token.required' => 'Le token d\'inscription est obligatoire.',
            'answers.required' => 'Les réponses sont obligatoires.',
            'answers.*.preselection_question_id.required' => 'L\'identifiant de la question est obligatoire.',
            'answers.*.preselection_question_id.exists' => 'Cette question de pré-sélection n\'existe pas.',
        ];
    }
}
