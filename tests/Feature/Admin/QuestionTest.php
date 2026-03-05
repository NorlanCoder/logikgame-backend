<?php

use App\Enums\QuestionStatus;
use App\Models\Question;

beforeEach(function () {
    $setup = createSessionSetup();
    $this->admin = $setup['admin'];
    $this->token = $setup['token'];
    $this->session = $setup['session'];
    $this->rounds = $setup['rounds'];
});

describe('GET /api/admin/sessions/{session}/rounds/{round}/questions', function () {
    it('lists questions for a round', function () {
        $round = $this->rounds[1];
        Question::factory()->count(3)->create(['session_round_id' => $round->id]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson("/api/admin/sessions/{$this->session->id}/rounds/{$round->id}/questions");

        $response->assertSuccessful()
            ->assertJsonCount(3, 'data');
    });
});

describe('POST /api/admin/sessions/{session}/rounds/{round}/questions', function () {
    it('creates a QCM question with choices', function () {
        $round = $this->rounds[1];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/admin/sessions/{$this->session->id}/rounds/{$round->id}/questions", [
                'text' => 'Quelle est la capitale de la France ?',
                'answer_type' => 'qcm',
                'correct_answer' => 'Paris',
                'duration' => 30,
                'display_order' => 1,
                'choices' => [
                    ['label' => 'Paris', 'is_correct' => true, 'display_order' => 1],
                    ['label' => 'Lyon', 'is_correct' => false, 'display_order' => 2],
                    ['label' => 'Marseille', 'is_correct' => false, 'display_order' => 3],
                    ['label' => 'Toulouse', 'is_correct' => false, 'display_order' => 4],
                ],
            ]);

        $response->assertStatus(201);

        $question = Question::first();
        expect($question->choices)->toHaveCount(4);
        expect($question->choices->where('is_correct', true)->first()->label)->toBe('Paris');
    });

    it('creates a question with hint for round 2', function () {
        $round = $this->rounds[2];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/admin/sessions/{$this->session->id}/rounds/{$round->id}/questions", [
                'text' => 'Question avec indice ?',
                'answer_type' => 'qcm',
                'correct_answer' => 'A',
                'duration' => 30,
                'display_order' => 1,
                'choices' => [
                    ['label' => 'A', 'is_correct' => true, 'display_order' => 1],
                    ['label' => 'B', 'is_correct' => false, 'display_order' => 2],
                    ['label' => 'C', 'is_correct' => false, 'display_order' => 3],
                    ['label' => 'D', 'is_correct' => false, 'display_order' => 4],
                ],
                'hint' => [
                    'hint_type' => 'remove_choices',
                    'time_penalty_seconds' => 5,
                    'removed_choice_ids' => [],
                ],
            ]);

        $response->assertStatus(201);

        $question = Question::first();
        expect($question->hint)->not->toBeNull();
    });

    it('creates a numeric question', function () {
        $round = $this->rounds[1];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/admin/sessions/{$this->session->id}/rounds/{$round->id}/questions", [
                'text' => 'Combien font 2 + 2 ?',
                'answer_type' => 'number',
                'correct_answer' => '4',
                'duration' => 20,
                'display_order' => 1,
            ]);

        $response->assertStatus(201);
    });

    it('validates required fields', function () {
        $round = $this->rounds[1];

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson("/api/admin/sessions/{$this->session->id}/rounds/{$round->id}/questions", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['text', 'answer_type', 'correct_answer']);
    });
});

describe('PUT /api/admin/sessions/{session}/rounds/{round}/questions/{question}', function () {
    it('updates a pending question', function () {
        $round = $this->rounds[1];
        $question = Question::factory()->create([
            'session_round_id' => $round->id,
            'status' => QuestionStatus::Pending,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson("/api/admin/sessions/{$this->session->id}/rounds/{$round->id}/questions/{$question->id}", [
                'text' => 'Question modifiée ?',
            ]);

        $response->assertSuccessful();
        expect($question->fresh()->text)->toBe('Question modifiée ?');
    });

    it('rejects update on non-pending question', function () {
        $round = $this->rounds[1];
        $question = Question::factory()->create([
            'session_round_id' => $round->id,
            'status' => QuestionStatus::Launched,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->putJson("/api/admin/sessions/{$this->session->id}/rounds/{$round->id}/questions/{$question->id}", [
                'text' => 'Should fail',
            ]);

        $response->assertStatus(422);
    });
});

describe('DELETE /api/admin/sessions/{session}/rounds/{round}/questions/{question}', function () {
    it('deletes a pending question', function () {
        $round = $this->rounds[1];
        $question = Question::factory()->create([
            'session_round_id' => $round->id,
            'status' => QuestionStatus::Pending,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->deleteJson("/api/admin/sessions/{$this->session->id}/rounds/{$round->id}/questions/{$question->id}");

        $response->assertSuccessful();
        expect(Question::find($question->id))->toBeNull();
    });
});
