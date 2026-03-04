<?php

namespace Database\Factories;

use App\Models\SecondChanceQuestion;
use App\Models\SecondChanceQuestionChoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SecondChanceQuestionChoice>
 */
class SecondChanceQuestionChoiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'second_chance_question_id' => SecondChanceQuestion::factory(),
            'label' => fake()->sentence(3),
            'is_correct' => false,
            'display_order' => fake()->numberBetween(1, 4),
        ];
    }

    public function correct(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_correct' => true,
        ]);
    }
}
