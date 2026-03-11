<?php

namespace App\Events;

use App\Models\SecondChanceQuestion;
use App\Models\Session;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SecondChanceLaunched implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Session $session,
        public SecondChanceQuestion $secondChanceQuestion,
        public int $mainQuestionId,
        public array $failedPlayerIds,
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
        return 'second_chance.launched';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->secondChanceQuestion->load('choices');

        return [
            'main_question_id' => $this->mainQuestionId,
            'failed_player_ids' => $this->failedPlayerIds,
            'question' => [
                'id' => $this->secondChanceQuestion->id,
                'text' => $this->secondChanceQuestion->text,
                'answer_type' => $this->secondChanceQuestion->answer_type,
                'media_url' => $this->secondChanceQuestion->media_url ? asset('storage/'.$this->secondChanceQuestion->media_url) : null,
                'media_type' => $this->secondChanceQuestion->media_type,
                'duration' => $this->secondChanceQuestion->duration,
                'launched_at' => $this->secondChanceQuestion->launched_at?->toIso8601String(),
                'choices' => $this->secondChanceQuestion->choices->map(fn ($c) => [
                    'id' => $c->id,
                    'label' => $c->label,
                    'display_order' => $c->display_order,
                ]),
            ],
        ];
    }
}
