<?php

use App\Models\PreselectionQuestion;

beforeEach(function () {
    $setup = createSessionSetup();
    $this->admin = $setup['admin'];
    $this->token = $setup['token'];
    $this->session = $setup['session'];
});

function presUrl(\App\Models\Session $session, string $suffix = ''): string
{
    return "/api/admin/sessions/{$session->id}/preselection-questions".($suffix ? "/$suffix" : '');
}

describe('GET /api/admin/sessions/{session}/preselection-questions', function () {
    it('lists preselection questions', function () {
        PreselectionQuestion::factory()->count(3)->create([
            'session_id' => $this->session->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson(presUrl($this->session));

        $response->assertSuccessful()
            ->assertJsonCount(3, 'data');
    });
});

describe('POST /api/admin/sessions/{session}/preselection-questions', function () {
    it('creates a QCM preselection question with choices', function () {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson(presUrl($this->session), [
                'text' => 'Quelle est la couleur du ciel ?',
                'answer_type' => 'qcm',
                'correct_answer' => 'Bleu',
                'duration' => 20,
                'display_order' => 1,
                'choices' => [
                    ['label' => 'Bleu', 'is_correct' => true, 'display_order' => 1],
                    ['label' => 'Rouge', 'is_correct' => false, 'display_order' => 2],
                    ['label' => 'Vert', 'is_correct' => false, 'display_order' => 3],
                    ['label' => 'Jaune', 'is_correct' => false, 'display_order' => 4],
                ],
            ]);

        $response->assertStatus(201);

        $pq = PreselectionQuestion::first();
        expect($pq->choices)->toHaveCount(4);
    });

    it('validates required fields', function () {
        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson(presUrl($this->session), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['text', 'answer_type', 'correct_answer']);
    });
});

describe('GET /api/admin/sessions/{session}/preselection-questions/{id}', function () {
    it('shows a preselection question', function () {
        $pq = PreselectionQuestion::factory()->create([
            'session_id' => $this->session->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson(presUrl($this->session, $pq->id));

        $response->assertSuccessful()
            ->assertJsonFragment(['id' => $pq->id]);
    });
});

describe('PUT /api/admin/sessions/{session}/preselection-questions/{id}', function () {
    it('updates a preselection question', function () {
        $pq = PreselectionQuestion::factory()->create([
            'session_id' => $this->session->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson(presUrl($this->session, $pq->id), [
                'text' => 'Question modifiée ?',
            ]);

        $response->assertSuccessful();
        expect($pq->fresh()->text)->toBe('Question modifiée ?');
    });
});

describe('DELETE /api/admin/sessions/{session}/preselection-questions/{id}', function () {
    it('deletes a preselection question', function () {
        $pq = PreselectionQuestion::factory()->create([
            'session_id' => $this->session->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->deleteJson(presUrl($this->session, $pq->id));

        $response->assertSuccessful();
        expect(PreselectionQuestion::find($pq->id))->toBeNull();
    });
});
