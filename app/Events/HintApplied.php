<?php

namespace App\Events;

use App\Models\SessionPlayer;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HintApplied implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public SessionPlayer $sessionPlayer,
        public int $questionId,
        /** @var array<string, mixed> */
        public array $hintData,
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
        return 'hint.applied';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'question_id' => $this->questionId,
            'hint' => $this->hintData,
        ];
    }
}
