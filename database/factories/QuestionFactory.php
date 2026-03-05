<?php

namespace Database\Factories;

use App\Enums\AnswerType;
use App\Enums\MediaType;
use App\Enums\QuestionStatus;
use App\Models\Question;
use App\Models\SessionRound;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_round_id' => SessionRound::factory(),
            'text' => fake()->sentence().' ?',
            'media_type' => MediaType::None,
            'media_url' => null,
            'answer_type' => AnswerType::Qcm,
            'correct_answer' => fake()->word(),
            'number_is_decimal' => false,
            'duration' => 30,
            'display_order' => fake()->numberBetween(1, 20),
            'status' => QuestionStatus::Pending,
        ];
    }

    public function numeric(): static
    {
        return $this->state(fn (array $attributes) => [
            'answer_type' => AnswerType::Number,
            'correct_answer' => (string) fake()->numberBetween(1, 1000),
        ]);
    }

    public function freeText(): static
    {
        return $this->state(fn (array $attributes) => [
            'answer_type' => AnswerType::Text,
            'correct_answer' => fake()->word(),
        ]);
    }

    public function withImage(): static
    {
        return $this->state(fn (array $attributes) => [
            'media_type' => MediaType::Image,
            'media_url' => fake()->imageUrl(),
        ]);
    }
}
