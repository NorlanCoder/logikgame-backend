<?php

use App\Enums\AnswerType;
use App\Enums\QuestionStatus;
use App\Enums\RoundStatus;
use App\Enums\SessionPlayerStatus;
use App\Enums\SessionStatus;
use App\Models\Player;
use App\Models\PlayerAnswer;
use App\Models\Question;
use App\Models\QuestionChoice;
use App\Models\Registration;
use App\Models\Session;
use App\Models\SessionPlayer;
use App\Models\SessionRound;

// ─────────────────────────────────────────────
//  Helpers
// ─────────────────────────────────────────────

function gameUrl(Session $session, string $path): string
{
    return "/api/admin/sessions/{$session->id}/game/{$path}";
}

function createQcmQuestion(SessionRound $round, int $order = 1): array
{
    $question = Question::factory()->create([
        'session_round_id' => $round->id,
        'answer_type' => AnswerType::Qcm,
        'correct_answer' => 'Bonne',
        'duration' => 30,
        'display_order' => $order,
        'status' => QuestionStatus::Pending,
    ]);

    $correct = QuestionChoice::factory()->correct()->create([
        'question_id' => $question->id,
        'label' => 'Bonne',
        'display_order' => 1,
    ]);

    $wrong = QuestionChoice::factory()->create([
        'question_id' => $question->id,
        'label' => 'Mauvaise',
        'display_order' => 2,
    ]);

    return ['question' => $question, 'correct' => $correct, 'wrong' => $wrong];
}

function createActivePlayers(Session $session, int $count = 5): \Illuminate\Support\Collection
{
    return collect(range(1, $count))->map(function () use ($session) {
        $player = Player::factory()->create();
        $registration = Registration::factory()->selected()->create([
            'session_id' => $session->id,
            'player_id' => $player->id,
        ]);

        return SessionPlayer::factory()->active()->create([
            'session_id' => $session->id,
            'player_id' => $player->id,
            'registration_id' => $registration->id,
            'capital' => 1000,
        ]);
    });
}

// ─────────────────────────────────────────────
//  Phase pré-jeu
// ─────────────────────────────────────────────

describe('Game Engine — Pre-game phase', function () {
    beforeEach(function () {
        $setup = createSessionSetup();
        $this->admin = $setup['admin'];
        $this->token = $setup['token'];
        $this->session = $setup['session'];
        $this->rounds = $setup['rounds'];
    });

    it('follows the session lifecycle: Draft → Preselection → RegistrationClosed → Ready → InProgress', function () {
        $h = fn () => $this->withHeader('Authorization', "Bearer {$this->token}");

        // Open preselection (inscriptions + quiz actifs en une étape)
        $h()->postJson(gameUrl($this->session, 'open-registration'))
            ->assertSuccessful();
        expect($this->session->fresh()->status)->toBe(SessionStatus::Preselection);

        // Register players (possible pendant la pré-sélection)
        $players = Player::factory()->count(5)->create();
        $registrations = $players->map(fn ($p) => Registration::factory()->create([
            'session_id' => $this->session->id,
            'player_id' => $p->id,
        ]));

        // Close preselection
        $h()->postJson(gameUrl($this->session, 'close-registration'))
            ->assertSuccessful();
        expect($this->session->fresh()->status)->toBe(SessionStatus::RegistrationClosed);

        // Select players (sans changement de statut)
        $h()->postJson(gameUrl($this->session, 'select-players'), [
            'registration_ids' => $registrations->pluck('id')->toArray(),
        ])->assertSuccessful();

        $this->session->refresh();
        expect($this->session->status)->toBe(SessionStatus::RegistrationClosed);
        expect($this->session->players_remaining)->toBe(5);
        expect(SessionPlayer::where('session_id', $this->session->id)->count())->toBe(5);

        // Confirm selection → passe à Ready
        $h()->postJson(gameUrl($this->session, 'confirm-selection'))
            ->assertSuccessful();

        $this->session->refresh();
        expect($this->session->status)->toBe(SessionStatus::Ready);

        // Start session
        $h()->postJson(gameUrl($this->session, 'start'))
            ->assertSuccessful();

        $this->session->refresh();
        expect($this->session->status)->toBe(SessionStatus::InProgress);
        expect($this->session->current_round_id)->not->toBeNull();
    });

    it('rejects open-registration on non-draft session', function () {
        $this->session->update(['status' => SessionStatus::InProgress]);

        $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson(gameUrl($this->session, 'open-registration'))
            ->assertStatus(422);
    });
});

// ─────────────────────────────────────────────
//  Manche 1 — Mort subite
// ─────────────────────────────────────────────

describe('Game Engine — Round 1 (Sudden Death)', function () {
    beforeEach(function () {
        $setup = createSessionSetup();
        $this->admin = $setup['admin'];
        $this->token = $setup['token'];
        $this->session = $setup['session'];
        $this->rounds = $setup['rounds'];

        // Prepare session in progress
        $round1 = $this->rounds[1];
        $round1->update(['status' => RoundStatus::InProgress, 'started_at' => now()]);
        $this->session->update([
            'status' => SessionStatus::InProgress,
            'current_round_id' => $round1->id,
            'started_at' => now(),
        ]);

        $this->players = createActivePlayers($this->session, 5);
        $this->session->update(['players_remaining' => 5]);
    });

    it('launches a question', function () {
        ['question' => $q] = createQcmQuestion($this->rounds[1]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson(gameUrl($this->session, 'launch-question'), [
                'question_id' => $q->id,
            ]);

        $response->assertSuccessful()
            ->assertJsonFragment(['question_id' => $q->id]);

        expect($q->fresh()->status)->toBe(QuestionStatus::Launched);
        expect($this->session->fresh()->current_question_id)->toBe($q->id);
    });

    it('eliminates players with wrong answers on close-question', function () {
        ['question' => $q, 'correct' => $correctChoice, 'wrong' => $wrongChoice] = createQcmQuestion($this->rounds[1]);
        $q->update(['status' => QuestionStatus::Launched, 'launched_at' => now()]);
        $this->session->update(['current_question_id' => $q->id]);

        // 3 players answer correctly, 2 answer wrong
        foreach ($this->players->take(3) as $sp) {
            PlayerAnswer::factory()->correct()->create([
                'session_player_id' => $sp->id,
                'question_id' => $q->id,
                'answer_value' => 'Bonne',
                'selected_choice_id' => $correctChoice->id,
            ]);
        }
        foreach ($this->players->skip(3) as $sp) {
            PlayerAnswer::factory()->create([
                'session_player_id' => $sp->id,
                'question_id' => $q->id,
                'answer_value' => 'Mauvaise',
                'selected_choice_id' => $wrongChoice->id,
            ]);
        }

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson(gameUrl($this->session, 'close-question'));

        $response->assertSuccessful();

        // Verify eliminations
        $eliminated = SessionPlayer::where('session_id', $this->session->id)
            ->where('status', SessionPlayerStatus::Eliminated)
            ->count();
        expect($eliminated)->toBe(2);

        // Verify question status
        expect($q->fresh()->status)->toBe(QuestionStatus::Closed);
    });

    it('reveals the answer', function () {
        ['question' => $q] = createQcmQuestion($this->rounds[1]);
        $q->update(['status' => QuestionStatus::Closed, 'launched_at' => now(), 'closed_at' => now()]);
        $this->session->update(['current_question_id' => $q->id]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson(gameUrl($this->session, 'reveal-answer'));

        $response->assertSuccessful();
        expect($q->fresh()->status)->toBe(QuestionStatus::Revealed);
    });

    it('advances to next round', function () {
        $round1 = $this->rounds[1];
        $round1->update(['status' => RoundStatus::InProgress]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson(gameUrl($this->session, 'next-round'));

        $response->assertSuccessful();

        $round1->refresh();
        expect($round1->status)->toBe(RoundStatus::Completed);

        $this->session->refresh();
        expect($this->session->current_round_id)->toBe($this->rounds[2]->id);
    });
});

// ─────────────────────────────────────────────
//  Manche 5 — Top 4 Elimination
// ─────────────────────────────────────────────

describe('Game Engine — Round 5 (Top 4)', function () {
    beforeEach(function () {
        $setup = createSessionSetup();
        $this->admin = $setup['admin'];
        $this->token = $setup['token'];
        $this->session = $setup['session'];
        $this->rounds = $setup['rounds'];

        $round5 = $this->rounds[5];
        $round5->update(['status' => RoundStatus::InProgress, 'started_at' => now()]);
        $this->session->update([
            'status' => SessionStatus::InProgress,
            'current_round_id' => $round5->id,
            'started_at' => now(),
        ]);

        $this->players = createActivePlayers($this->session, 8);
        $this->session->update(['players_remaining' => 8]);
    });

    it('finalizes top 4 and eliminates the rest', function () {
        // Create multiple questions already answered for ranking
        $q1Data = createQcmQuestion($this->rounds[5], 1);
        $q1Data['question']->update(['status' => QuestionStatus::Closed, 'closed_at' => now()]);

        // Players 1-4 answered correctly, 5-8 answered wrong
        foreach ($this->players->take(4) as $i => $sp) {
            PlayerAnswer::factory()->correct()->create([
                'session_player_id' => $sp->id,
                'question_id' => $q1Data['question']->id,
                'response_time_ms' => 1000 + ($i * 100),
            ]);
        }
        foreach ($this->players->skip(4) as $sp) {
            PlayerAnswer::factory()->create([
                'session_player_id' => $sp->id,
                'question_id' => $q1Data['question']->id,
                'is_correct' => false,
                'response_time_ms' => 5000,
            ]);
        }

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson(gameUrl($this->session, 'finalize-top4'));

        $response->assertSuccessful();

        $active = SessionPlayer::where('session_id', $this->session->id)
            ->where('status', SessionPlayerStatus::Active)
            ->count();
        $eliminated = SessionPlayer::where('session_id', $this->session->id)
            ->where('status', SessionPlayerStatus::Eliminated)
            ->count();

        expect($active)->toBe(4)
            ->and($eliminated)->toBe(4);
    });
});

// ─────────────────────────────────────────────
//  End Session
// ─────────────────────────────────────────────

describe('Game Engine — End Session', function () {
    it('ends an in-progress session', function () {
        $setup = createSessionSetup();
        $setup['session']->update([
            'status' => SessionStatus::InProgress,
            'started_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$setup['token']}")
            ->postJson(gameUrl($setup['session'], 'end'));

        $response->assertSuccessful();
        expect($setup['session']->fresh()->status)->toBe(SessionStatus::Ended);
    });
});
