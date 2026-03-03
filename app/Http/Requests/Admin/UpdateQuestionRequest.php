<?php

namespace App\Http\Requests\Admin;

use App\Enums\AnswerType;
use App\Enums\MediaType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuestionRequest extends FormRequest
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
            'text' => ['sometimes', 'string', 'max:2000'],
            'answer_type' => ['sometimes', Rule::enum(AnswerType::class)],
            'correct_answer' => ['sometimes', 'string', 'max:500'],
            'duration' => ['sometimes', 'integer', 'min:5', 'max:300'],
            'display_order' => ['nullable', 'integer', 'min:1'],
            'media_url' => ['nullable', 'url', 'max:500'],
            'media_type' => ['nullable', Rule::enum(MediaType::class)],
            'number_is_decimal' => ['nullable', 'boolean'],
        ];
    }
}
