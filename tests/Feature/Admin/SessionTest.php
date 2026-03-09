<?php

use App\Enums\SessionStatus;
use App\Models\Session;

beforeEach(function () {
    ['admin' => $this->admin, 'token' => $this->token] = createAdminWithToken();
});

describe('GET /api/admin/sessions', function () {
    it('lists sessions for the authenticated admin', function () {
        Session::factory()->count(3)->create(['admin_id' => $this->admin->id]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/admin/sessions');

        $response->assertSuccessful()
            ->assertJsonCount(3, 'data');
    });

    it('does not list sessions of other admins', function () {
        Session::factory()->count(2)->create(); // other admin

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/admin/sessions');

        $response->assertSuccessful()
            ->assertJsonCount(0, 'data');
    });
});

describe('POST /api/admin/sessions', function () {
    it('creates a session with 8 default rounds', function () {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/admin/sessions', [
                'name' => 'Test Session',
                'scheduled_at' => now()->addDays(7)->toIso8601String(),
                'max_players' => 100,
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Test Session']);

        $session = Session::first();
        expect($session->rounds)->toHaveCount(8);
    });

    it('validates required fields', function () {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/admin/sessions', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'scheduled_at', 'max_players']);
    });

    it('rejects past scheduled_at', function () {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/admin/sessions', [
                'name' => 'Past Session',
                'scheduled_at' => now()->subDay()->toIso8601String(),
                'max_players' => 50,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['scheduled_at']);
    });
});

describe('GET /api/admin/sessions/{session}', function () {
    it('shows a session detail', function () {
        $session = Session::factory()->create(['admin_id' => $this->admin->id]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/admin/sessions/{$session->id}");

        $response->assertSuccessful()
            ->assertJsonFragment(['id' => $session->id]);
    });
});

describe('PUT /api/admin/sessions/{session}', function () {
    it('updates a draft session', function () {
        $session = Session::factory()->create([
            'admin_id' => $this->admin->id,
            'status' => SessionStatus::Draft,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson("/api/admin/sessions/{$session->id}", [
                'name' => 'Updated Name',
            ]);

        $response->assertSuccessful();
        expect($session->fresh()->name)->toBe('Updated Name');
    });

    it('allows updating metadata fields on in-progress session', function () {
        $session = Session::factory()->inProgress()->create([
            'admin_id' => $this->admin->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson("/api/admin/sessions/{$session->id}", [
                'name' => 'Updated In Progress',
                'description' => 'Nouvelle description',
                'max_players' => 200,
            ]);

        $response->assertSuccessful();
        $session->refresh();
        expect($session->name)->toBe('Updated In Progress')
            ->and($session->description)->toBe('Nouvelle description')
            ->and($session->max_players)->toBe(200);
    });

    it('rejects restricted fields on in-progress session', function () {
        $session = Session::factory()->inProgress()->create([
            'admin_id' => $this->admin->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson("/api/admin/sessions/{$session->id}", [
                'reconnection_delay' => 30,
            ]);

        $response->assertStatus(422);
    });

    it('rejects update on ended session', function () {
        $session = Session::factory()->ended()->create([
            'admin_id' => $this->admin->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson("/api/admin/sessions/{$session->id}", [
                'name' => 'Should Fail',
            ]);

        $response->assertStatus(422);
    });
});

describe('DELETE /api/admin/sessions/{session}', function () {
    it('deletes a draft session', function () {
        $session = Session::factory()->create([
            'admin_id' => $this->admin->id,
            'status' => SessionStatus::Draft,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->deleteJson("/api/admin/sessions/{$session->id}");

        $response->assertSuccessful();
        expect(Session::find($session->id))->toBeNull();
    });

    it('rejects delete on non-draft session', function () {
        $session = Session::factory()->registrationOpen()->create([
            'admin_id' => $this->admin->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->deleteJson("/api/admin/sessions/{$session->id}");

        $response->assertStatus(422);
    });
});
