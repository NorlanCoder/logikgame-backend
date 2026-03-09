<?php

namespace App\Events;

use App\Models\Session;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuestionClosed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Session $session,
        public int $questionId,
        public int $answersReceived,
        public int $correctCount,
        public int $eliminatedCount,
        public array $inDangerPlayers = [],
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
        return 'question.closed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'question_id' => $this->questionId,
            'answers_received' => $this->answersReceived,
            'correct_count' => $this->correctCount,
            'eliminated_count' => $this->eliminatedCount,
            'in_danger_count' => count($this->inDangerPlayers),
            'in_danger_players' => $this->inDangerPlayers,
        ];
    }
}
