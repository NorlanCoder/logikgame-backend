<?php

namespace Database\Factories;

use App\Enums\HintType;
use App\Models\Question;
use App\Models\QuestionHint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuestionHint>
 */
class QuestionHintFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'question_id' => Question::factory(),
            'hint_type' => HintType::RemoveChoices,
            'time_penalty_seconds' => 5,
            'removed_choice_ids' => null,
            'revealed_letters' => null,
            'range_hint_text' => null,
            'range_min' => null,
            'range_max' => null,
        ];
    }

    public function removeChoices(array $choiceIds = [1, 2]): static
    {
        return $this->state(fn (array $attributes) => [
            'hint_type' => HintType::RemoveChoices,
            'removed_choice_ids' => $choiceIds,
        ]);
    }

    public function revealLetters(array $letters = ['a', 'b']): static
    {
        return $this->state(fn (array $attributes) => [
            'hint_type' => HintType::RevealLetters,
            'revealed_letters' => $letters,
        ]);
    }

    public function reduceRange(float $min = 10, float $max = 50): static
    {
        return $this->state(fn (array $attributes) => [
            'hint_type' => HintType::ReduceRange,
            'range_hint_text' => "Entre $min et $max",
            'range_min' => $min,
            'range_max' => $max,
        ]);
    }
}
