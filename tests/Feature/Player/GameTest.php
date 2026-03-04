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
use App\Models\QuestionHint;
use App\Models\Registration;
use App\Models\Session;
use App\Models\SessionPlayer;
use App\Models\SessionRound;
use Illuminate\Support\Str;

/**
 * Setup a playable session with a player.
 *
 * @return array{session: Session, round: SessionRound, player: SessionPlayer, token: string}
 */
function setupPlayerGame(): array
{
    $setup = createSessionSetup();
    $session = $setup['session'];
    $round = $setup['rounds'][1];

    $round->update(['status' => RoundStatus::InProgress, 'started_at' => now()]);
    $session->update([
        'status' => SessionStatus::InProgress,
        'current_round_id' => $round->id,
        'started_at' => now(),
    ]);

    $player = Player::factory()->create();
    $registration = Registration::factory()->selected()->create([
        'session_id' => $session->id,
        'player_id' => $player->id,
    ]);

    $accessToken = Str::random(64);
    $sp = SessionPlayer::factory()->active()->create([
        'session_id' => $session->id,
        'player_id' => $player->id,
        'registration_id' => $registration->id,
        'access_token' => $accessToken,
        'capital' => 1000,
    ]);

    $session->update(['players_remaining' => 1]);

    return [
        'session' => $session,
        'round' => $round,
        'rounds' => $setup['rounds'],
        'player' => $sp,
        'token' => $accessToken,
        'adminToken' => $setup['token'],
    ];
}

describe('POST /api/player/join', function () {
    it('joins the game with a valid access token', function () {
        $game = setupPlayerGame();

        $response = $this->withHeader('X-Player-Token', $game['token'])
            ->postJson('/api/player/join');

        $response->assertSuccessful();
    });

    it('rejects invalid access token', function () {
        $response = $this->withHeader('X-Player-Token', 'invalid-token')
            ->postJson('/api/player/join');

        $response->assertUnauthorized();
    });

    it('rejects request without token', function () {
        $response = $this->postJson('/api/player/join');

        $response->assertUnauthorized();
    });
});

describe('GET /api/player/status', function () {
    it('returns current game status', function () {
        $game = setupPlayerGame();

        $response = $this->withHeader('X-Player-Token', $game['token'])
            ->getJson('/api/player/status');

        $response->assertSuccessful()
            ->assertJsonStructure([
                'session',
                'my_status',
                'current_round',
            ]);
    });
});

describe('POST /api/player/answer', function () {
    it('submits a correct answer', function () {
        $game = setupPlayerGame();

        $question = Question::factory()->create([
            'session_round_id' => $game['round']->id,
            'answer_type' => AnswerType::Qcm,
            'correct_answer' => 'Paris',
            'status' => QuestionStatus::Launched,
            'launched_at' => now(),
            'duration' => 30,
            'display_order' => 1,
        ]);

        $correct = QuestionChoice::factory()->correct()->create([
            'question_id' => $question->id,
            'label' => 'Paris',
        ]);

        $game['session']->update(['current_question_id' => $question->id]);

        $response = $this->withHeader('X-Player-Token', $game['token'])
            ->postJson('/api/player/answer', [
                'question_id' => $question->id,
                'answer_value' => 'Paris',
                'selected_choice_id' => $correct->id,
                'response_time_ms' => 5000,
            ]);

        $response->assertSuccessful();
        expect(PlayerAnswer::where('session_player_id', $game['player']->id)->exists())->toBeTrue();
    });

    it('prevents duplicate answer submission', function () {
        $game = setupPlayerGame();

        $question = Question::factory()->create([
            'session_round_id' => $game['round']->id,
            'answer_type' => AnswerType::Qcm,
            'correct_answer' => 'A',
            'status' => QuestionStatus::Launched,
            'launched_at' => now(),
            'duration' => 30,
            'display_order' => 1,
        ]);
        $game['session']->update(['current_question_id' => $question->id]);

        $correct = QuestionChoice::factory()->correct()->create([
            'question_id' => $question->id,
            'label' => 'A',
        ]);

        // First answer
        PlayerAnswer::factory()->create([
            'session_player_id' => $game['player']->id,
            'question_id' => $question->id,
        ]);

        // Second answer attempt
        $response = $this->withHeader('X-Player-Token', $game['token'])
            ->postJson('/api/player/answer', [
                'question_id' => $question->id,
                'answer_value' => 'A',
                'selected_choice_id' => $correct->id,
                'response_time_ms' => 3000,
            ]);

        $response->assertStatus(422);
    });
});

describe('POST /api/player/hint', function () {
    it('uses a hint in round 2', function () {
        $game = setupPlayerGame();

        // Switch to round 2 (Hint round)
        $round2 = $game['rounds'][2];
        $round2->update(['status' => RoundStatus::InProgress, 'started_at' => now()]);
        $game['session']->update(['current_round_id' => $round2->id]);

        $question = Question::factory()->create([
            'session_round_id' => $round2->id,
            'answer_type' => AnswerType::Qcm,
            'correct_answer' => 'A',
            'status' => QuestionStatus::Launched,
            'launched_at' => now(),
            'duration' => 30,
            'display_order' => 1,
        ]);

        $hint = QuestionHint::factory()->removeChoices()->create([
            'question_id' => $question->id,
            'time_penalty_seconds' => 5,
        ]);

        $game['session']->update(['current_question_id' => $question->id]);

        $response = $this->withHeader('X-Player-Token', $game['token'])
            ->postJson('/api/player/hint', [
                'question_id' => $question->id,
            ]);

        $response->assertSuccessful();
    });
});

describe('POST /api/player/pass-manche', function () {
    it('allows passing in round 4 with sufficient capital', function () {
        $game = setupPlayerGame();

        // Switch to round 4
        $round4 = $game['rounds'][4];
        $round4->update(['status' => RoundStatus::InProgress, 'started_at' => now()]);
        $game['session']->update(['current_round_id' => $round4->id]);

        $response = $this->withHeader('X-Player-Token', $game['token'])
            ->postJson('/api/player/pass-manche');

        $response->assertSuccessful();

        $game['player']->refresh();
        expect($game['player']->capital)->toBe(0); // 1000 - 1000
    });

    it('rejects passing with insufficient capital', function () {
        $game = setupPlayerGame();

        $round4 = $game['rounds'][4];
        $round4->update(['status' => RoundStatus::InProgress, 'started_at' => now()]);
        $game['session']->update(['current_round_id' => $round4->id]);

        // Set capital to 0
        $game['player']->update(['capital' => 0]);

        $response = $this->withHeader('X-Player-Token', $game['token'])
            ->postJson('/api/player/pass-manche');

        $response->assertStatus(422);
    });
});

describe('POST /api/player/finale-choice', function () {
    it('submits a finale choice for a finalist', function () {
        $game = setupPlayerGame();

        // Switch to round 8 (Finale)
        $round8 = $game['rounds'][8];
        $round8->update(['status' => RoundStatus::InProgress, 'started_at' => now()]);
        $game['session']->update(['current_round_id' => $round8->id]);

        // Player must be finalist
        $game['player']->update(['status' => SessionPlayerStatus::Finalist]);

        $response = $this->withHeader('X-Player-Token', $game['token'])
            ->postJson('/api/player/finale-choice', [
                'choice' => 'continue',
            ]);

        $response->assertSuccessful();
    });

    it('rejects finale choice from non-finalist', function () {
        $game = setupPlayerGame();

        $round8 = $game['rounds'][8];
        $round8->update(['status' => RoundStatus::InProgress, 'started_at' => now()]);
        $game['session']->update(['current_round_id' => $round8->id]);

        // Player stays Active (not Finalist)
        $response = $this->withHeader('X-Player-Token', $game['token'])
            ->postJson('/api/player/finale-choice', [
                'choice' => 'continue',
            ]);

        $response->assertStatus(422); // Not a finalist
    });
});
