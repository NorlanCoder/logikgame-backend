<?php

use App\Models\Admin;

beforeEach(function () {
    $this->admin = Admin::factory()->create([
        'email' => 'test@logikgame.com',
        'password' => bcrypt('password123'),
    ]);
});

describe('POST /api/admin/login', function () {
    it('authenticates with valid credentials', function () {
        $response = $this->postJson('/api/admin/login', [
            'email' => 'test@logikgame.com',
            'password' => 'password123',
        ]);

        $response->assertSuccessful()
            ->assertJsonStructure([
                'admin' => ['id', 'name', 'email'],
                'token',
            ]);
    });

    it('rejects invalid credentials', function () {
        $response = $this->postJson('/api/admin/login', [
            'email' => 'test@logikgame.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401);
    });

    it('rejects inactive admin', function () {
        $this->admin->update(['is_active' => false]);

        $response = $this->postJson('/api/admin/login', [
            'email' => 'test@logikgame.com',
            'password' => 'password123',
        ]);

        $response->assertUnauthorized();
    });

    it('validates required fields', function () {
        $response = $this->postJson('/api/admin/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    });
});

describe('POST /api/admin/logout', function () {
    it('logs out authenticated admin', function () {
        ['token' => $token] = createAdminWithToken();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/admin/logout');

        $response->assertSuccessful();
    });

    it('rejects unauthenticated request', function () {
        $response = $this->postJson('/api/admin/logout');

        $response->assertUnauthorized();
    });
});

describe('GET /api/admin/me', function () {
    it('returns authenticated admin profile', function () {
        ['admin' => $admin, 'token' => $token] = createAdminWithToken();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/admin/me');

        $response->assertSuccessful()
            ->assertJsonFragment([
                'id' => $admin->id,
                'email' => $admin->email,
            ]);
    });

    it('rejects unauthenticated request', function () {
        $response = $this->getJson('/api/admin/me');

        $response->assertUnauthorized();
    });
});
