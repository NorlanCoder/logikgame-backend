<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreSessionRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'max_players' => ['required', 'integer', 'min:2', 'max:1000'],
            'description' => ['nullable', 'string', 'max:5000'],
            'cover_image' => ['nullable', 'file', 'image', 'max:5120'],
            'registration_opens_at' => ['nullable', 'date'],
            'registration_closes_at' => ['nullable', 'date', 'after:registration_opens_at'],
            'preselection_opens_at' => ['nullable', 'date'],
            'preselection_closes_at' => ['nullable', 'date', 'after:preselection_opens_at'],
            'reconnection_delay' => ['nullable', 'integer', 'min:5', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Le nom de la session est obligatoire.',
            'scheduled_at.required' => 'La date de la session est obligatoire.',
            'scheduled_at.after' => 'La session doit être planifiée dans le futur.',
            'max_players.required' => 'Le nombre maximum de joueurs est obligatoire.',
            'max_players.min' => 'Il faut au minimum 2 joueurs.',
        ];
    }
}
