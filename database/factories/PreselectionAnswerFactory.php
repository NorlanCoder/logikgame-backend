<?php

namespace Database\Factories;

use App\Models\PreselectionAnswer;
use App\Models\PreselectionQuestion;
use App\Models\Registration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PreselectionAnswer>
 */
class PreselectionAnswerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'registration_id' => Registration::factory(),
            'preselection_question_id' => PreselectionQuestion::factory(),
            'answer_value' => fake()->word(),
            'is_correct' => false,
            'response_time_ms' => fake()->numberBetween(500, 20000),
            'submitted_at' => now(),
        ];
    }

    public function correct(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_correct' => true,
        ]);
    }
}
