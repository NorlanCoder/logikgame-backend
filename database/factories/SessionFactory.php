<?php

namespace Database\Factories;

use App\Enums\SessionStatus;
use App\Models\Admin;
use App\Models\Session;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Session>
 */
class SessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $scheduledAt = fake()->dateTimeBetween('+1 day', '+30 days');

        return [
            'admin_id' => Admin::factory(),
            'name' => 'LOGIK S'.fake()->numberBetween(1, 10).'E'.fake()->numberBetween(1, 20),
            'description' => fake()->paragraph(),
            'cover_image_url' => null,
            'scheduled_at' => $scheduledAt,
            'max_players' => fake()->randomElement([50, 100, 200, 500]),
            'status' => SessionStatus::Draft,
            'registration_opens_at' => null,
            'registration_closes_at' => null,
            'preselection_opens_at' => null,
            'preselection_closes_at' => null,
            'jackpot' => 0,
            'players_remaining' => 0,
            'reconnection_delay' => 10,
            'projection_code' => strtoupper(fake()->bothify('??####')),
        ];
    }

    public function registrationOpen(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SessionStatus::RegistrationOpen,
            'registration_opens_at' => now()->subDay(),
            'registration_closes_at' => now()->addDays(7),
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SessionStatus::InProgress,
            'started_at' => now(),
        ]);
    }

    public function ended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SessionStatus::Ended,
            'started_at' => now()->subHours(2),
            'ended_at' => now(),
        ]);
    }
}
