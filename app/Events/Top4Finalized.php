<?php

namespace App\Events;

use App\Models\Session;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class Top4Finalized implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Session $session,
        /** @var array<int, array{pseudo: string, correct_answers_count: int, total_response_time_ms: int, rank: int, is_qualified: bool}> */
        public array $rankings,
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
        return 'top4.finalized';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'rankings' => $this->rankings,
        ];
    }
}
