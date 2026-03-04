<?php

use App\Enums\RegistrationStatus;
use App\Enums\SessionStatus;
use App\Events\GameEnded;
use App\Events\QuestionLaunched;
use App\Events\RoundStarted;
use App\Models\Player;
use App\Models\Registration;
use App\Models\Session;
use App\Notifications\PlayerRejected;
use App\Notifications\PlayerSelected;
use App\Notifications\RegistrationConfirmed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

// ─────────────────────────────────────────────
//  Notifications — Inscription
// ─────────────────────────────────────────────

describe('Notification — RegistrationConfirmed', function () {
    it('sends RegistrationConfirmed when a player registers', function () {
        Notification::fake();

        $session = Session::factory()->registrationOpen()->create();

        $response = $this->postJson('/api/player/register', [
            'session_id' => $session->id,
            'full_name' => 'Alice Martin',
            'email' => 'alice@example.com',
            'phone' => '+33601020304',
            'pseudo' => 'AliceM',
        ]);

        $response->assertStatus(201);

        $player = Player::where('email', 'alice@example.com')->first();

        Notification::assertSentTo($player, RegistrationConfirmed::class, function ($notification) use ($session) {
            return $notification->registration->session_id === $session->id;
        });
    });

    it('does not resend notification on duplicate registration', function () {
        Notification::fake();

        $session = Session::factory()->registrationOpen()->create();
        $player = Player::factory()->create();
        Registration::factory()->create([
            'session_id' => $session->id,
            'player_id' => $player->id,
        ]);

        $this->postJson('/api/player/register', [
            'session_id' => $session->id,
            'full_name' => $player->full_name,
            'email' => $player->email,
            'phone' => $player->phone,
            'pseudo' => 'AnyPseudo',
        ])->assertStatus(200);

        Notification::assertNothingSent();
    });
});

// ─────────────────────────────────────────────
//  Notifications — Sélection / Rejet
// ─────────────────────────────────────────────

describe('Notification — Player Selection', function () {
    it('sends PlayerSelected to selected players', function () {
        Notification::fake();

        ['token' => $token, 'session' => $session] = createSessionSetup([
            'status' => SessionStatus::Preselection,
        ]);

        $players = Player::factory()->count(3)->create();
        $registrations = $players->map(fn ($p) => Registration::factory()->create([
            'session_id' => $session->id,
            'player_id' => $p->id,
        ]));

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/admin/sessions/{$session->id}/game/select-players", [
                'registration_ids' => $registrations->pluck('id')->toArray(),
            ])->assertSuccessful();

        foreach ($players as $player) {
            Notification::assertSentTo($player, PlayerSelected::class);
        }
    });

    it('sends PlayerRejected to rejected players', function () {
        Notification::fake();

        ['token' => $token, 'session' => $session] = createSessionSetup([
            'status' => SessionStatus::Preselection,
        ]);

        $selectedPlayer = Player::factory()->create();
        $rejectedPlayer = Player::factory()->create();

        $selectedReg = Registration::factory()->create([
            'session_id' => $session->id,
            'player_id' => $selectedPlayer->id,
        ]);
        $rejectedReg = Registration::factory()->create([
            'session_id' => $session->id,
            'player_id' => $rejectedPlayer->id,
            'status' => RegistrationStatus::Selected,
        ]);

        // Select only the first player → the second (previously selected) gets rejected
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/admin/sessions/{$session->id}/game/select-players", [
                'registration_ids' => [$selectedReg->id],
            ])->assertSuccessful();

        Notification::assertSentTo($selectedPlayer, PlayerSelected::class);
        Notification::assertSentTo($rejectedPlayer, PlayerRejected::class);
    });
});

// ─────────────────────────────────────────────
//  Broadcasting Events — Lifecycle
// ─────────────────────────────────────────────

describe('Broadcasting — Game Lifecycle Events', function () {
    it('dispatches RoundStarted when session starts', function () {
        Event::fake([RoundStarted::class]);

        ['token' => $token, 'session' => $session] = createSessionSetup([
            'status' => SessionStatus::Ready,
            'players_remaining' => 5,
        ]);

        // Create active players
        createActivePlayers($session, 5);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/admin/sessions/{$session->id}/game/start")
            ->assertSuccessful();

        Event::assertDispatched(RoundStarted::class, function ($event) use ($session) {
            return $event->session->id === $session->id;
        });
    });

    it('dispatches QuestionLaunched when launching a question', function () {
        Event::fake([QuestionLaunched::class]);

        ['token' => $token, 'session' => $session, 'rounds' => $rounds] = createSessionSetup([
            'status' => SessionStatus::InProgress,
        ]);
        $rounds[1]->update(['status' => \App\Enums\RoundStatus::InProgress, 'started_at' => now()]);
        $session->update(['current_round_id' => $rounds[1]->id]);

        $q = createQcmQuestion($rounds[1]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/admin/sessions/{$session->id}/game/launch-question", [
                'question_id' => $q['question']->id,
            ])->assertSuccessful();

        Event::assertDispatched(QuestionLaunched::class);
    });

    it('dispatches GameEnded when session ends', function () {
        Event::fake([GameEnded::class]);

        ['token' => $token, 'session' => $session] = createSessionSetup([
            'status' => SessionStatus::InProgress,
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/admin/sessions/{$session->id}/game/end")
            ->assertSuccessful();

        Event::assertDispatched(GameEnded::class, function ($event) use ($session) {
            return $event->session->id === $session->id;
        });
    });
});
