<?php

namespace Database\Factories;

use App\Models\PreselectionQuestion;
use App\Models\PreselectionQuestionChoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PreselectionQuestionChoice>
 */
class PreselectionQuestionChoiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'preselection_question_id' => PreselectionQuestion::factory(),
            'label' => fake()->sentence(3),
            'is_correct' => false,
            'display_order' => fake()->numberBetween(1, 6),
        ];
    }

    public function correct(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_correct' => true,
        ]);
    }
}
