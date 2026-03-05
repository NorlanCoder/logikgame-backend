<?php

use App\Models\Player;
use App\Models\Registration;
use App\Models\Session;

describe('POST /api/player/register', function () {
    it('registers a new player for a session', function () {
        $session = Session::factory()->registrationOpen()->create();

        $response = $this->postJson('/api/player/register', [
            'session_id' => $session->id,
            'full_name' => 'Jean Dupont',
            'email' => 'jean@example.com',
            'phone' => '+33612345678',
            'pseudo' => 'JeanD',
        ]);

        $response->assertStatus(201);
        expect(Player::where('email', 'jean@example.com')->exists())->toBeTrue();
        expect(Registration::where('session_id', $session->id)->exists())->toBeTrue();
    });

    it('reuses existing player by email', function () {
        $session = Session::factory()->registrationOpen()->create();
        $player = Player::factory()->create(['email' => 'existing@example.com']);

        $response = $this->postJson('/api/player/register', [
            'session_id' => $session->id,
            'full_name' => $player->full_name,
            'email' => 'existing@example.com',
            'phone' => $player->phone,
            'pseudo' => 'NewPseudo',
        ]);

        $response->assertStatus(201);
        expect(Player::where('email', 'existing@example.com')->count())->toBe(1);
    });

    it('prevents duplicate registration for same session', function () {
        $session = Session::factory()->registrationOpen()->create();
        $player = Player::factory()->create();
        Registration::factory()->create([
            'session_id' => $session->id,
            'player_id' => $player->id,
        ]);

        $response = $this->postJson('/api/player/register', [
            'session_id' => $session->id,
            'full_name' => $player->full_name,
            'email' => $player->email,
            'phone' => $player->phone,
            'pseudo' => 'UniquePseudo',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Vous êtes déjà inscrit à cette session.']);
    });

    it('validates required fields', function () {
        $response = $this->postJson('/api/player/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['session_id', 'full_name', 'email', 'pseudo']);
    });

    it('requires a valid session', function () {
        $response = $this->postJson('/api/player/register', [
            'session_id' => 99999,
            'full_name' => 'Test',
            'email' => 'test@test.com',
            'phone' => '0600000000',
            'pseudo' => 'TestPseudo',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['session_id']);
    });
});

describe('GET /api/player/registrations/{registration}', function () {
    it('shows registration details', function () {
        $registration = Registration::factory()->create();

        $response = $this->getJson("/api/player/registrations/{$registration->id}");

        $response->assertSuccessful()
            ->assertJsonFragment(['id' => $registration->id]);
    });
});
