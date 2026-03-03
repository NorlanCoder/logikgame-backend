<?php

namespace Database\Factories;

use App\Enums\RoundStatus;
use App\Enums\RoundType;
use App\Models\Session;
use App\Models\SessionRound;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionRound>
 */
class SessionRoundFactory extends Factory
{
    /**
     * @var array<int, array{type: RoundType, name: string}>
     */
    private static array $roundDefaults = [
        1 => ['type' => RoundType::SuddenDeath, 'name' => 'Mort subite'],
        2 => ['type' => RoundType::Hint, 'name' => 'Utilisation d\'indice'],
        3 => ['type' => RoundType::SecondChance, 'name' => 'Seconde chance'],
        4 => ['type' => RoundType::RoundSkip, 'name' => 'Passage de manche'],
        5 => ['type' => RoundType::Top4Elimination, 'name' => 'Élimination pour les 4 finalistes'],
        6 => ['type' => RoundType::DuelJackpot, 'name' => 'Duel — Tour de rôle (cagnotte)'],
        7 => ['type' => RoundType::DuelElimination, 'name' => 'Duel — Élimination'],
        8 => ['type' => RoundType::Finale, 'name' => 'Finale'],
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $roundNumber = fake()->numberBetween(1, 8);

        return [
            'session_id' => Session::factory(),
            'round_number' => $roundNumber,
            'round_type' => self::$roundDefaults[$roundNumber]['type'],
            'name' => self::$roundDefaults[$roundNumber]['name'],
            'is_active' => true,
            'status' => RoundStatus::Pending,
            'display_order' => $roundNumber,
            'rules_description' => null,
        ];
    }

    public function roundNumber(int $number): static
    {
        return $this->state(fn (array $attributes) => [
            'round_number' => $number,
            'round_type' => self::$roundDefaults[$number]['type'],
            'name' => self::$roundDefaults[$number]['name'],
            'display_order' => $number,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
