<?php

namespace App\Events;

use App\Models\Session;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FinaleChoicesRevealed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Session $session,
        /** @var array<int, array{session_player_id: int, choice: string, pseudo: string}> */
        public array $choices,
        public string $scenario,
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
        return 'finale.choices.revealed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'choices' => $this->choices,
            'scenario' => $this->scenario,
        ];
    }
}
