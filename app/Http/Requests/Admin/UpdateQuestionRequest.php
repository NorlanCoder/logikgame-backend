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
            'media_file' => ['nullable', 'file', 'max:51200', 'mimes:jpg,jpeg,png,gif,webp,mp4,webm,mov,mp3,wav,ogg,aac'],
            'media_type' => ['nullable', Rule::enum(MediaType::class)],
            'number_is_decimal' => ['nullable', 'boolean'],
            'remove_media' => ['nullable', 'boolean'],
        ];
    }
}
