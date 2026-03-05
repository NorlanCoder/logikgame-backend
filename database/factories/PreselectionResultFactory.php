<?php

namespace Database\Factories;

use App\Models\PreselectionResult;
use App\Models\Registration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PreselectionResult>
 */
class PreselectionResultFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'registration_id' => Registration::factory(),
            'correct_answers_count' => fake()->numberBetween(0, 10),
            'total_questions' => 10,
            'total_response_time_ms' => fake()->numberBetween(10000, 60000),
            'rank' => null,
            'is_selected' => false,
            'completed_at' => now(),
        ];
    }

    public function selected(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_selected' => true,
        ]);
    }
}
