<?php

use App\Enums\QuestionStatus;
use App\Enums\SessionPlayerStatus;
use App\Models\Player;
use App\Models\ProjectionAccess;
use App\Models\Question;
use App\Models\QuestionChoice;
use App\Models\Session;
use App\Models\SessionPlayer;
use App\Models\SessionRound;

// ─────────────────────────────────────────────
//  Génération de code projection (admin)
// ─────────────────────────────────────────────

describe('POST /api/admin/sessions/{session}/projection/generate', function () {
    it('generates a projection code for a session', function () {
        ['token' => $token, 'session' => $session] = createSessionSetup();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/admin/sessions/{$session->id}/projection/generate");

        $response->assertStatus(201)
            ->assertJsonStructure(['access_code', 'url']);

        expect(ProjectionAccess::where('session_id', $session->id)->exists())->toBeTrue();

        $code = $response->json('access_code');
        expect(strlen($code))->toBe(6);
        expect($code)->toBe(strtoupper($code));
    });

    it('requires admin authentication', function () {
        $session = Session::factory()->create();

        $this->postJson("/api/admin/sessions/{$session->id}/projection/generate")
            ->assertUnauthorized();
    });
});

// ─────────────────────────────────────────────
//  Authentification projection
// ─────────────────────────────────────────────

describe('POST /api/projection/authenticate', function () {
    it('authenticates with a valid access code', function () {
        $session = Session::factory()->create();
        $projection = ProjectionAccess::create([
            'session_id' => $session->id,
            'access_code' => 'ABCDEF',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/projection/authenticate', [
            'access_code' => 'ABCDEF',
        ]);

        $response->assertSuccessful()
            ->assertJsonFragment([
                'session_id' => $session->id,
                'session_name' => $session->name,
                'access_code' => 'ABCDEF',
            ]);
    });

    it('rejects an invalid access code', function () {
        $this->postJson('/api/projection/authenticate', [
            'access_code' => 'XXXXXX',
        ])->assertStatus(401);
    });

    it('rejects a disabled projection code', function () {
        $session = Session::factory()->create();
        ProjectionAccess::create([
            'session_id' => $session->id,
            'access_code' => 'DISABL',
            'is_active' => false,
        ]);

        $this->postJson('/api/projection/authenticate', [
            'access_code' => 'DISABL',
        ])->assertStatus(401);
    });

    it('validates access code format', function () {
        $this->postJson('/api/projection/authenticate', [
            'access_code' => 'AB',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['access_code']);
    });
});

// ─────────────────────────────────────────────
//  Synchronisation projection
// ─────────────────────────────────────────────

describe('GET /api/projection/{accessCode}/sync', function () {
    it('returns full session state', function () {
        $session = Session::factory()->inProgress()->create([
            'jackpot' => 5000,
            'players_remaining' => 3,
        ]);
        $round = SessionRound::factory()->create([
            'session_id' => $session->id,
            'round_number' => 1,
            'is_active' => true,
        ]);
        $session->update(['current_round_id' => $round->id]);

        $projection = ProjectionAccess::create([
            'session_id' => $session->id,
            'access_code' => 'SYNC01',
            'is_active' => true,
        ]);

        // Create some active players
        $players = Player::factory()->count(3)->create();
        foreach ($players as $player) {
            SessionPlayer::factory()->create([
                'session_id' => $session->id,
                'player_id' => $player->id,
                'status' => SessionPlayerStatus::Active,
            ]);
        }

        $response = $this->getJson('/api/projection/SYNC01/sync');

        $response->assertSuccessful()
            ->assertJsonStructure([
                'session' => ['id', 'name', 'status', 'jackpot', 'players_remaining'],
                'current_round',
                'current_question',
                'active_players',
                'recent_eliminated',
            ]);

        expect($response->json('active_players'))->toHaveCount(3);
        expect($response->json('session.jackpot'))->toBe(5000);
    });

    it('hides correct answer when question is launched', function () {
        $session = Session::factory()->inProgress()->create();
        $round = SessionRound::factory()->create([
            'session_id' => $session->id,
            'round_number' => 1,
            'is_active' => true,
        ]);

        $question = Question::factory()->create([
            'session_round_id' => $round->id,
            'status' => QuestionStatus::Launched,
            'correct_answer' => 'Bonne',
            'launched_at' => now(),
        ]);

        QuestionChoice::factory()->correct()->create([
            'question_id' => $question->id,
            'label' => 'Bonne',
        ]);
        QuestionChoice::factory()->create([
            'question_id' => $question->id,
            'label' => 'Mauvaise',
        ]);

        $session->update([
            'current_round_id' => $round->id,
            'current_question_id' => $question->id,
        ]);

        $projection = ProjectionAccess::create([
            'session_id' => $session->id,
            'access_code' => 'SYNC02',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/projection/SYNC02/sync');

        $response->assertSuccessful();
        expect($response->json('current_question.correct_answer'))->toBeNull();
        // Choices should not include is_correct
        $choices = $response->json('current_question.choices');
        foreach ($choices as $choice) {
            expect($choice)->not->toHaveKey('is_correct');
        }
    });

    it('shows correct answer after reveal', function () {
        $session = Session::factory()->inProgress()->create();
        $round = SessionRound::factory()->create([
            'session_id' => $session->id,
            'round_number' => 1,
            'is_active' => true,
        ]);

        $question = Question::factory()->create([
            'session_round_id' => $round->id,
            'status' => QuestionStatus::Revealed,
            'correct_answer' => 'Bonne',
        ]);

        QuestionChoice::factory()->correct()->create([
            'question_id' => $question->id,
            'label' => 'Bonne',
        ]);

        $session->update([
            'current_round_id' => $round->id,
            'current_question_id' => $question->id,
        ]);

        $projection = ProjectionAccess::create([
            'session_id' => $session->id,
            'access_code' => 'SYNC03',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/projection/SYNC03/sync');

        $response->assertSuccessful();
        expect($response->json('current_question.correct_answer'))->toBe('Bonne');
    });

    it('rejects an invalid projection code', function () {
        $this->getJson('/api/projection/BADCOD/sync')
            ->assertStatus(401);
    });
});
