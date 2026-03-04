<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'scheduled_at' => ['sometimes', 'date'],
            'max_players' => ['sometimes', 'integer', 'min:2', 'max:1000'],
            'description' => ['nullable', 'string', 'max:5000'],
            'cover_image_url' => ['nullable', 'url', 'max:500'],
            'registration_opens_at' => ['nullable', 'date'],
            'registration_closes_at' => ['nullable', 'date'],
            'preselection_opens_at' => ['nullable', 'date'],
            'preselection_closes_at' => ['nullable', 'date'],
            'reconnection_delay' => ['nullable', 'integer', 'min:5', 'max:120'],
        ];
    }
}
