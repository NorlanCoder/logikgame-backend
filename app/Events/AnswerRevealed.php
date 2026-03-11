<?php

namespace App\Events;

use App\Models\Session;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AnswerRevealed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Session $session,
        public int $questionId,
        public string $correctAnswer,
        /** @var array<int, array{id: int, label: string, is_correct: bool}> */
        public array $choices = [],
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel("session.{$this->session->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'answer.revealed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'question_id' => $this->questionId,
            'correct_answer' => $this->correctAnswer,
            'choices' => $this->choices,
        ];
    }
}
