<?php

namespace Database\Factories;

use App\Models\PlayerAnswer;
use App\Models\Question;
use App\Models\SessionPlayer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlayerAnswer>
 */
class PlayerAnswerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_player_id' => SessionPlayer::factory(),
            'question_id' => Question::factory(),
            'is_second_chance' => false,
            'answer_value' => fake()->word(),
            'is_correct' => false,
            'hint_used' => false,
            'response_time_ms' => fake()->numberBetween(500, 25000),
            'submitted_at' => now(),
            'is_timeout' => false,
        ];
    }

    public function correct(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_correct' => true,
        ]);
    }

    public function timeout(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_timeout' => true,
            'is_correct' => false,
        ]);
    }

    public function secondChance(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_second_chance' => true,
        ]);
    }
}
