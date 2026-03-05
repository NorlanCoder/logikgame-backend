<?php

namespace Database\Factories;

use App\Enums\SessionPlayerStatus;
use App\Models\Player;
use App\Models\Registration;
use App\Models\Session;
use App\Models\SessionPlayer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SessionPlayer>
 */
class SessionPlayerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => Session::factory(),
            'player_id' => Player::factory(),
            'registration_id' => Registration::factory(),
            'access_token' => Str::uuid()->toString(),
            'status' => SessionPlayerStatus::Waiting,
            'capital' => 1000,
            'personal_jackpot' => 0,
            'is_connected' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SessionPlayerStatus::Active,
            'is_connected' => true,
            'last_connected_at' => now(),
        ]);
    }

    public function eliminated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SessionPlayerStatus::Eliminated,
            'eliminated_at' => now(),
        ]);
    }

    public function finalist(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SessionPlayerStatus::Finalist,
        ]);
    }
}
