<?php

namespace App\Http\Requests\Player;

use Illuminate\Foundation\Http\FormRequest;

class StoreRegistrationRequest extends FormRequest
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
        $sessionId = $this->input('session_id');

        return [
            'session_id' => ['required', 'integer', 'exists:game_sessions,id'],
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'pseudo' => ['required', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'session_id.required' => 'La session est obligatoire.',
            'session_id.exists' => 'Cette session n\'existe pas.',
            'full_name.required' => 'Le nom complet est obligatoire.',
            'email.required' => 'L\'adresse e-mail est obligatoire.',
            'email.unique' => 'Cette adresse e-mail est déjà inscrite à cette session.',
            'phone.required' => 'Le numéro de téléphone est obligatoire.',
            'pseudo.required' => 'Le pseudo est obligatoire.',
            'pseudo.unique' => 'Ce pseudo est déjà pris pour cette session.',
        ];
    }
}
