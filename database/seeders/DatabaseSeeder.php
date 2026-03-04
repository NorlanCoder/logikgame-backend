<?php

namespace Database\Seeders;

use App\Enums\AnswerType;
use App\Enums\RegistrationStatus;
use App\Enums\RoundType;
use App\Models\Admin;
use App\Models\Player;
use App\Models\PreselectionQuestion;
use App\Models\PreselectionQuestionChoice;
use App\Models\Question;
use App\Models\QuestionChoice;
use App\Models\QuestionHint;
use App\Models\Registration;
use App\Models\SecondChanceQuestion;
use App\Models\SecondChanceQuestionChoice;
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
        // --- Admin ---
        $admin = Admin::factory()->create([
            'name' => 'Admin LogikGame',
            'email' => 'admin@logikgame.com',
        ]);

        // --- Session de démonstration ---
        $session = Session::factory()->create([
            'admin_id' => $admin->id,
            'name' => 'LOGIK S01E01 — Démo',
            'description' => 'Session de démonstration pour le développement.',
            'max_players' => 100,
        ]);

        // --- 8 manches ---
        $roundDefs = [
            1 => ['type' => RoundType::SuddenDeath, 'name' => 'Mort subite'],
            2 => ['type' => RoundType::Hint, 'name' => 'Utilisation d\'indice'],
            3 => ['type' => RoundType::SecondChance, 'name' => 'Seconde chance'],
            4 => ['type' => RoundType::RoundSkip, 'name' => 'Passage de manche'],
            5 => ['type' => RoundType::Top4Elimination, 'name' => 'Élimination Top 4'],
            6 => ['type' => RoundType::DuelJackpot, 'name' => 'Duel — Cagnotte'],
            7 => ['type' => RoundType::DuelElimination, 'name' => 'Duel — Élimination'],
            8 => ['type' => RoundType::Finale, 'name' => 'Finale'],
        ];

        $rounds = [];
        foreach ($roundDefs as $number => $def) {
            $rounds[$number] = SessionRound::factory()->create([
                'session_id' => $session->id,
                'round_number' => $number,
                'round_type' => $def['type'],
                'name' => $def['name'],
                'is_active' => true,
                'display_order' => $number,
            ]);
        }

        // --- Questions pour chaque manche (3 par manche, manches 1-5) ---
        foreach ([1, 2, 3, 4, 5] as $roundNum) {
            for ($q = 1; $q <= 3; $q++) {
                $question = Question::factory()->create([
                    'session_round_id' => $rounds[$roundNum]->id,
                    'text' => "Question $q — Manche $roundNum ?",
                    'answer_type' => AnswerType::Qcm,
                    'correct_answer' => 'Bonne réponse',
                    'duration' => 30,
                    'display_order' => $q,
                ]);

                // 4 choix QCM
                QuestionChoice::factory()->correct()->create([
                    'question_id' => $question->id,
                    'label' => 'Bonne réponse',
                    'display_order' => 1,
                ]);
                for ($c = 2; $c <= 4; $c++) {
                    QuestionChoice::factory()->create([
                        'question_id' => $question->id,
                        'label' => "Mauvaise réponse $c",
                        'display_order' => $c,
                    ]);
                }

                // Indice pour manche 2
                if ($roundNum === 2) {
                    QuestionHint::factory()->removeChoices()->create([
                        'question_id' => $question->id,
                        'time_penalty_seconds' => 5,
                    ]);
                }

                // Question de seconde chance pour manche 3
                if ($roundNum === 3) {
                    $sc = SecondChanceQuestion::factory()->create([
                        'main_question_id' => $question->id,
                        'text' => "Seconde chance — Q$q Manche 3 ?",
                        'answer_type' => AnswerType::Qcm,
                        'correct_answer' => 'SC Bonne',
                        'duration' => 15,
                    ]);
                    SecondChanceQuestionChoice::factory()->correct()->create([
                        'second_chance_question_id' => $sc->id,
                        'label' => 'SC Bonne',
                        'display_order' => 1,
                    ]);
                    for ($sc_c = 2; $sc_c <= 4; $sc_c++) {
                        SecondChanceQuestionChoice::factory()->create([
                            'second_chance_question_id' => $sc->id,
                            'label' => "SC Mauvaise $sc_c",
                            'display_order' => $sc_c,
                        ]);
                    }
                }
            }
        }

        // --- Questions pour manches 6 et 7 (duels, 4 questions chacune) ---
        foreach ([6, 7] as $roundNum) {
            for ($q = 1; $q <= 4; $q++) {
                $question = Question::factory()->create([
                    'session_round_id' => $rounds[$roundNum]->id,
                    'text' => "Duel Q$q — Manche $roundNum ?",
                    'answer_type' => AnswerType::Qcm,
                    'correct_answer' => 'Correct',
                    'duration' => 20,
                    'display_order' => $q,
                ]);
                QuestionChoice::factory()->correct()->create([
                    'question_id' => $question->id,
                    'label' => 'Correct',
                    'display_order' => 1,
                ]);
                for ($c = 2; $c <= 4; $c++) {
                    QuestionChoice::factory()->create([
                        'question_id' => $question->id,
                        'label' => "Faux $c",
                        'display_order' => $c,
                    ]);
                }
            }
        }

        // --- 1 question finale (manche 8) ---
        $finaleQ = Question::factory()->create([
            'session_round_id' => $rounds[8]->id,
            'text' => 'Question finale — Tout ou rien ?',
            'answer_type' => AnswerType::Qcm,
            'correct_answer' => 'Victoire',
            'duration' => 30,
            'display_order' => 1,
        ]);
        QuestionChoice::factory()->correct()->create([
            'question_id' => $finaleQ->id,
            'label' => 'Victoire',
            'display_order' => 1,
        ]);
        for ($c = 2; $c <= 4; $c++) {
            QuestionChoice::factory()->create([
                'question_id' => $finaleQ->id,
                'label' => "Défaite $c",
                'display_order' => $c,
            ]);
        }

        // --- Questions de pré-sélection (5 questions) ---
        for ($pq = 1; $pq <= 5; $pq++) {
            $presQ = PreselectionQuestion::factory()->create([
                'session_id' => $session->id,
                'text' => "Pré-sélection Q$pq ?",
                'answer_type' => AnswerType::Qcm,
                'correct_answer' => 'Vrai',
                'duration' => 20,
                'display_order' => $pq,
            ]);
            PreselectionQuestionChoice::factory()->correct()->create([
                'preselection_question_id' => $presQ->id,
                'label' => 'Vrai',
                'display_order' => 1,
            ]);
            for ($pc = 2; $pc <= 4; $pc++) {
                PreselectionQuestionChoice::factory()->create([
                    'preselection_question_id' => $presQ->id,
                    'label' => "Faux $pc",
                    'display_order' => $pc,
                ]);
            }
        }

        // --- 20 joueurs inscrits ---
        $players = Player::factory()->count(20)->create();
        foreach ($players as $player) {
            Registration::factory()->create([
                'session_id' => $session->id,
                'player_id' => $player->id,
                'status' => RegistrationStatus::Registered,
            ]);
        }
    }
}
