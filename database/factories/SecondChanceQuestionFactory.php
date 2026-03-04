<?php

namespace Database\Factories;

use App\Enums\AnswerType;
use App\Enums\MediaType;
use App\Enums\QuestionStatus;
use App\Models\Question;
use App\Models\SecondChanceQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SecondChanceQuestion>
 */
class SecondChanceQuestionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'main_question_id' => Question::factory(),
            'text' => fake()->sentence().' ?',
            'media_type' => MediaType::None,
            'media_url' => null,
            'answer_type' => AnswerType::Qcm,
            'correct_answer' => fake()->word(),
            'number_is_decimal' => false,
            'duration' => 20,
            'status' => QuestionStatus::Pending,
        ];
    }
}
