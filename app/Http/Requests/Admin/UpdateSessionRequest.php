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
        $session = $this->route('session');
        $isPreGame = $session && in_array($session->status, [
            \App\Enums\SessionStatus::Draft,
            \App\Enums\SessionStatus::RegistrationOpen,
        ]);

        $scheduledAtRules = ['sometimes', 'date'];
        if ($isPreGame) {
            $scheduledAtRules[] = 'after:now';
        }

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'scheduled_at' => $scheduledAtRules,
            'max_players' => ['sometimes', 'integer', 'min:2', 'max:1000'],
            'description' => ['nullable', 'string', 'max:5000'],
            'cover_image' => ['nullable', 'file', 'image', 'max:5120'],
            'remove_cover_image' => ['nullable', 'boolean'],
            'registration_opens_at' => ['nullable', 'date'],
            'registration_closes_at' => ['nullable', 'date'],
            'preselection_opens_at' => ['nullable', 'date'],
            'preselection_closes_at' => ['nullable', 'date'],
            'reconnection_delay' => ['nullable', 'integer', 'min:5', 'max:120'],
        ];
    }
}
