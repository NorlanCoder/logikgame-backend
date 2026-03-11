<?php

namespace App\Console\Commands;

use App\Enums\SessionPlayerStatus;
use App\Enums\SessionStatus;
use App\Models\Elimination;
use App\Models\FinaleChoice;
use App\Models\FinalResult;
use App\Models\HintUsage;
use App\Models\JackpotTransaction;
use App\Models\PlayerAnswer;
use App\Models\Question;
use App\Models\Round6PlayerJackpot;
use App\Models\Round6TurnOrder;
use App\Models\RoundRanking;
use App\Models\RoundSkip;
use App\Models\SecondChanceQuestion;
use App\Models\Session;
use Illuminate\Console\Command;

class ResetSessionToPreselection extends Command
{
    protected $signature = 'session:preselection {session : ID de la session}';

    protected $description = 'Remet une session en état Preselection en nettoyant les données de jeu mais en gardant les joueurs sélectionnés.';

    public function handle(): int
    {
        $session = Session::find($this->argument('session'));

        if (! $session) {
            $this->error('Session introuvable.');

            return self::FAILURE;
        }

        $this->info("Session #{$session->id} — {$session->name}");
        $this->info("Statut actuel : {$session->status->value}");

        $selectedCount = $session->sessionPlayers()->count();
        $this->info("Joueurs sélectionnés : {$selectedCount}");

        if (! $this->confirm('Remettre cette session en Preselection ? Les données de jeu seront supprimées mais les joueurs sélectionnés seront conservés.')) {
            return self::SUCCESS;
        }

        $roundIds = $session->rounds()->pluck('id');
        $playerIds = $session->sessionPlayers()->pluck('id');

        // Supprimer les données de jeu
        PlayerAnswer::whereIn('session_player_id', $playerIds)->delete();
        HintUsage::whereIn('session_player_id', $playerIds)->delete();
        Elimination::whereIn('session_player_id', $playerIds)->delete();
        RoundSkip::whereIn('session_player_id', $playerIds)->delete();
        RoundRanking::whereIn('session_round_id', $roundIds)->delete();
        Round6TurnOrder::whereIn('session_round_id', $roundIds)->delete();
        Round6PlayerJackpot::whereIn('session_round_id', $roundIds)->delete();
        FinaleChoice::where('session_id', $session->id)->delete();
        FinalResult::where('session_id', $session->id)->delete();
        JackpotTransaction::where('session_id', $session->id)->delete();

        // Reset questions
        $questionIds = Question::whereIn('session_round_id', $roundIds)->pluck('id');

        Question::whereIn('session_round_id', $roundIds)->update([
            'status' => 'pending',
            'launched_at' => null,
            'closed_at' => null,
            'revealed_at' => null,
        ]);

        SecondChanceQuestion::whereIn('main_question_id', $questionIds)->update([
            'status' => 'pending',
            'launched_at' => null,
            'closed_at' => null,
        ]);

        // Reset rounds
        $session->rounds()->update([
            'status' => 'pending',
            'started_at' => null,
            'ended_at' => null,
        ]);

        // Reset session players (garder les enregistrements)
        $session->sessionPlayers()->update([
            'status' => SessionPlayerStatus::Waiting,
            'capital' => 1000,
            'personal_jackpot' => 0,
            'final_gain' => null,
            'is_connected' => false,
            'last_connected_at' => null,
            'eliminated_at' => null,
            'elimination_reason' => null,
            'eliminated_in_round_id' => null,
        ]);

        // Reset session → Preselection
        $session->update([
            'status' => SessionStatus::Preselection,
            'current_round_id' => null,
            'current_question_id' => null,
            'jackpot' => 0,
            'players_remaining' => $selectedCount,
            'started_at' => null,
            'ended_at' => null,
        ]);

        $this->info("Session remise en Preselection avec succès ({$selectedCount} joueurs conservés).");

        return self::SUCCESS;
    }
}
