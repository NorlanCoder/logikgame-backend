<?php

namespace Database\Seeders;

use App\Enums\RoundType;
use App\Models\Admin;
use App\Models\Session;
use App\Models\SessionRound;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Créer un admin par défaut
        $admin = Admin::factory()->create([
            'name' => 'Admin LogikGame',
            'email' => 'admin@logikgame.com',
        ]);

        // Créer une session de démonstration
        $session = Session::factory()->create([
            'admin_id' => $admin->id,
            'name' => 'LOGIK S01E01 — Démo',
            'description' => 'Session de démonstration pour le développement.',
            'max_players' => 100,
        ]);

        // Créer les 8 manches pour cette session
        $rounds = [
            1 => ['type' => RoundType::SuddenDeath, 'name' => 'Mort subite'],
            2 => ['type' => RoundType::Hint, 'name' => 'Utilisation d\'indice'],
            3 => ['type' => RoundType::SecondChance, 'name' => 'Seconde chance'],
            4 => ['type' => RoundType::RoundSkip, 'name' => 'Passage de manche'],
            5 => ['type' => RoundType::Top4Elimination, 'name' => 'Élimination Top 4'],
            6 => ['type' => RoundType::DuelJackpot, 'name' => 'Duel — Cagnotte'],
            7 => ['type' => RoundType::DuelElimination, 'name' => 'Duel — Élimination'],
            8 => ['type' => RoundType::Finale, 'name' => 'Finale'],
        ];

        foreach ($rounds as $number => $round) {
            SessionRound::factory()->create([
                'session_id' => $session->id,
                'round_number' => $number,
                'round_type' => $round['type'],
                'name' => $round['name'],
                'is_active' => $number >= 5 || in_array($number, [1, 2, 3]),
                'display_order' => $number,
            ]);
        }
    }
}
