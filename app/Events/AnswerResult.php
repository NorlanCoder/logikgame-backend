<?php

namespace App\Events;

use App\Models\SessionPlayer;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AnswerResult implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public SessionPlayer $sessionPlayer,
        public int $questionId,
        public bool $isCorrect,
        public ?string $correctAnswer = null,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("player.{$this->sessionPlayer->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'answer.result';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'question_id' => $this->questionId,
            'is_correct' => $this->isCorrect,
            'correct_answer' => $this->correctAnswer,
            'personal_jackpot' => $this->sessionPlayer->personal_jackpot,
        ];
    }
}
