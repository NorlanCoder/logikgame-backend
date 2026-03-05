<?php

namespace Database\Factories;

use App\Enums\RegistrationStatus;
use App\Models\Player;
use App\Models\Registration;
use App\Models\Session;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Registration>
 */
class RegistrationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => Session::factory(),
            'player_id' => Player::factory(),
            'status' => RegistrationStatus::Registered,
            'registered_at' => now(),
        ];
    }

    public function selected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RegistrationStatus::Selected,
            'selection_email_sent_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RegistrationStatus::Rejected,
            'rejection_email_sent_at' => now(),
        ]);
    }
}
