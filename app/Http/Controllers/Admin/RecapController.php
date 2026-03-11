<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Elimination;
use App\Models\FinaleChoice;
use App\Models\FinalResult;
use App\Models\HintUsage;
use App\Models\PlayerAnswer;
use App\Models\RoundRanking;
use App\Models\RoundSkip;
use App\Models\Session;
use App\Models\SessionPlayer;
use Illuminate\Http\JsonResponse;

class RecapController extends Controller
{
    public function show(Session $session): JsonResponse
    {
        // --- Players ---
        $sessionPlayers = SessionPlayer::query()
            ->where('session_id', $session->id)
            ->with('player:id,pseudo,full_name')
            ->get()
            ->keyBy('id');

        $players = $sessionPlayers->map(fn ($sp) => [
            'id' => $sp->id,
            'pseudo' => $sp->player->pseudo ?? 'Inconnu',
            'full_name' => $sp->player->full_name ?? '',
            'status' => $sp->status,
            'capital' => $sp->capital,
            'personal_jackpot' => $sp->personal_jackpot,
            'final_gain' => $sp->final_gain,
            'elimination_reason' => $sp->elimination_reason,
            'eliminated_in_round_id' => $sp->eliminated_in_round_id,
        ]);

        // --- Rounds with questions ---
        $session->load([
            'rounds.questions.choices',
            'rounds.questions.secondChanceQuestion.choices',
            'rounds.questions.hint',
        ]);

        $allAnswers = PlayerAnswer::query()
            ->whereHas('question', fn ($q) => $q->whereHas('sessionRound', fn ($r) => $r->where('session_id', $session->id)))
            ->with(['selectedChoice:id,label', 'selectedScChoice:id,label'])
            ->get()
            ->groupBy('question_id');

        $allHintUsages = HintUsage::query()
            ->whereIn('session_player_id', $sessionPlayers->keys())
            ->get()
            ->groupBy('question_id');

        $allEliminations = Elimination::query()
            ->whereIn('session_player_id', $sessionPlayers->keys())
            ->get();

        $eliminationsByRound = $allEliminations->groupBy('session_round_id');
        $eliminationsByQuestion = $allEliminations->groupBy('question_id');

        $allRoundSkips = RoundSkip::query()
            ->whereIn('session_player_id', $sessionPlayers->keys())
            ->get()
            ->groupBy('session_round_id');

        $allRankings = RoundRanking::query()
            ->whereIn('session_player_id', $sessionPlayers->keys())
            ->get()
            ->groupBy('session_round_id');

        // --- Build rounds data ---
        $rounds = $session->rounds->map(function ($round) use (
            $allAnswers,
            $allHintUsages,
            $eliminationsByRound,
            $eliminationsByQuestion,
            $allRoundSkips,
            $allRankings,
            $sessionPlayers,
        ) {
            $questions = $round->questions->map(function ($question) use (
                $allAnswers,
                $allHintUsages,
                $eliminationsByQuestion,
                $sessionPlayers,
            ) {
                $answers = $allAnswers->get($question->id, collect());
                $hintUsages = $allHintUsages->get($question->id, collect());
                $eliminations = $eliminationsByQuestion->get($question->id, collect());

                // Main question answers
                $mainAnswers = $answers->where('is_second_chance', false);
                $scAnswers = $answers->where('is_second_chance', true);

                $playerAnswers = $mainAnswers->map(function ($pa) use ($sessionPlayers) {
                    $player = $sessionPlayers->get($pa->session_player_id);

                    return [
                        'session_player_id' => $pa->session_player_id,
                        'pseudo' => $player ? ($player->player->pseudo ?? 'Inconnu') : 'Inconnu',
                        'answer_value' => $pa->answer_value,
                        'selected_choice' => $pa->selectedChoice?->label,
                        'is_correct' => $pa->is_correct,
                        'hint_used' => $pa->hint_used,
                        'response_time_ms' => $pa->response_time_ms,
                        'is_timeout' => $pa->is_timeout,
                    ];
                })->values();

                $scPlayerAnswers = $scAnswers->map(function ($pa) use ($sessionPlayers) {
                    $player = $sessionPlayers->get($pa->session_player_id);

                    return [
                        'session_player_id' => $pa->session_player_id,
                        'pseudo' => $player ? ($player->player->pseudo ?? 'Inconnu') : 'Inconnu',
                        'answer_value' => $pa->answer_value,
                        'selected_choice' => $pa->selectedScChoice?->label ?? $pa->selectedChoice?->label,
                        'is_correct' => $pa->is_correct,
                        'response_time_ms' => $pa->response_time_ms,
                        'is_timeout' => $pa->is_timeout,
                    ];
                })->values();

                $hintUsers = $hintUsages->map(function ($hu) use ($sessionPlayers) {
                    $player = $sessionPlayers->get($hu->session_player_id);

                    return [
                        'session_player_id' => $hu->session_player_id,
                        'pseudo' => $player ? ($player->player->pseudo ?? 'Inconnu') : 'Inconnu',
                    ];
                })->values();

                $questionEliminations = $eliminations->map(function ($e) use ($sessionPlayers) {
                    $player = $sessionPlayers->get($e->session_player_id);

                    return [
                        'session_player_id' => $e->session_player_id,
                        'pseudo' => $player ? ($player->player->pseudo ?? 'Inconnu') : 'Inconnu',
                        'reason' => $e->reason,
                        'capital_transferred' => $e->capital_transferred,
                    ];
                })->values();

                return [
                    'id' => $question->id,
                    'text' => $question->text,
                    'answer_type' => $question->answer_type,
                    'correct_answer' => $question->correct_answer,
                    'display_order' => $question->display_order,
                    'status' => $question->status,
                    'assigned_player_id' => $question->assigned_player_id,
                    'assigned_player_pseudo' => $question->assigned_player_id
                        ? ($sessionPlayers->get($question->assigned_player_id)?->player->pseudo ?? null)
                        : null,
                    'has_second_chance' => $question->secondChanceQuestion !== null,
                    'second_chance_text' => $question->secondChanceQuestion?->text,
                    'second_chance_correct' => $question->secondChanceQuestion?->correct_answer,
                    'stats' => [
                        'total_answers' => $mainAnswers->count(),
                        'correct_count' => $mainAnswers->where('is_correct', true)->count(),
                        'wrong_count' => $mainAnswers->where('is_correct', false)->where('is_timeout', false)->count(),
                        'timeout_count' => $mainAnswers->where('is_timeout', true)->count(),
                        'hint_used_count' => $mainAnswers->where('hint_used', true)->count(),
                        'avg_response_time_ms' => (int) $mainAnswers->where('is_timeout', false)->avg('response_time_ms'),
                        'sc_total' => $scAnswers->count(),
                        'sc_correct' => $scAnswers->where('is_correct', true)->count(),
                    ],
                    'player_answers' => $playerAnswers,
                    'sc_player_answers' => $scPlayerAnswers,
                    'hint_users' => $hintUsers,
                    'eliminations' => $questionEliminations,
                ];
            });

            // Round-level data
            $roundEliminations = ($eliminationsByRound->get($round->id, collect()))
                ->map(function ($e) use ($sessionPlayers) {
                    $player = $sessionPlayers->get($e->session_player_id);

                    return [
                        'session_player_id' => $e->session_player_id,
                        'pseudo' => $player ? ($player->player->pseudo ?? 'Inconnu') : 'Inconnu',
                        'reason' => $e->reason,
                        'question_id' => $e->question_id,
                        'capital_transferred' => $e->capital_transferred,
                    ];
                })->values();

            $roundSkips = ($allRoundSkips->get($round->id, collect()))
                ->map(function ($rs) use ($sessionPlayers) {
                    $player = $sessionPlayers->get($rs->session_player_id);

                    return [
                        'session_player_id' => $rs->session_player_id,
                        'pseudo' => $player ? ($player->player->pseudo ?? 'Inconnu') : 'Inconnu',
                        'capital_lost' => $rs->capital_lost,
                    ];
                })->values();

            $rankings = ($allRankings->get($round->id, collect()))
                ->sortBy('rank')
                ->map(function ($r) use ($sessionPlayers) {
                    $player = $sessionPlayers->get($r->session_player_id);

                    return [
                        'session_player_id' => $r->session_player_id,
                        'pseudo' => $player ? ($player->player->pseudo ?? 'Inconnu') : 'Inconnu',
                        'rank' => $r->rank,
                        'correct_answers_count' => $r->correct_answers_count,
                        'total_response_time_ms' => $r->total_response_time_ms,
                        'is_qualified' => $r->is_qualified,
                    ];
                })->values();

            return [
                'id' => $round->id,
                'round_number' => $round->round_number,
                'round_type' => $round->round_type,
                'name' => $round->name,
                'status' => $round->status,
                'questions' => $questions,
                'eliminations' => $roundEliminations,
                'round_skips' => $roundSkips,
                'rankings' => $rankings,
            ];
        });

        // --- Finale data ---
        $finaleChoices = FinaleChoice::query()
            ->where('session_id', $session->id)
            ->get()
            ->map(function ($fc) use ($sessionPlayers) {
                $player = $sessionPlayers->get($fc->session_player_id);

                return [
                    'session_player_id' => $fc->session_player_id,
                    'pseudo' => $player ? ($player->player->pseudo ?? 'Inconnu') : 'Inconnu',
                    'choice' => $fc->choice,
                ];
            })->values();

        $finalResults = FinalResult::query()
            ->where('session_id', $session->id)
            ->orderBy('position')
            ->get()
            ->map(function ($fr) use ($sessionPlayers) {
                $player = $sessionPlayers->get($fr->session_player_id);

                return [
                    'session_player_id' => $fr->session_player_id,
                    'pseudo' => $player ? ($player->player->pseudo ?? 'Inconnu') : 'Inconnu',
                    'finale_scenario' => $fr->finale_scenario,
                    'final_gain' => $fr->final_gain,
                    'is_winner' => $fr->is_winner,
                    'position' => $fr->position,
                ];
            })->values();

        return response()->json([
            'session' => [
                'id' => $session->id,
                'name' => $session->name,
                'status' => $session->status,
                'jackpot' => $session->jackpot,
                'players_remaining' => $session->players_remaining,
                'started_at' => $session->started_at,
                'ended_at' => $session->ended_at,
            ],
            'players' => $players->values(),
            'rounds' => $rounds,
            'finale' => [
                'choices' => $finaleChoices,
                'results' => $finalResults,
            ],
        ]);
    }
}
