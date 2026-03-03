<?php

namespace Database\Factories;

use App\Enums\AnswerType;
use App\Enums\MediaType;
use App\Models\PreselectionQuestion;
use App\Models\Session;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PreselectionQuestion>
 */
class PreselectionQuestionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => Session::factory(),
            'text' => fake()->sentence().' ?',
            'media_type' => MediaType::None,
            'media_url' => null,
            'answer_type' => AnswerType::Qcm,
            'correct_answer' => fake()->word(),
            'number_is_decimal' => false,
            'duration' => 30,
            'display_order' => fake()->numberBetween(1, 30),
        ];
    }
}
