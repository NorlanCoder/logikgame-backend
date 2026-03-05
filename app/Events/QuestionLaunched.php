<?php

namespace App\Events;

use App\Models\Question;
use App\Models\Session;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuestionLaunched implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Session $session,
        public Question $question,
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
        return 'question.launched';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->question->load('choices');

        return [
            'question' => [
                'id' => $this->question->id,
                'text' => $this->question->text,
                'answer_type' => $this->question->answer_type,
                'media_url' => $this->question->media_url,
                'media_type' => $this->question->media_type,
                'duration' => $this->question->duration,
                'launched_at' => $this->question->launched_at?->toIso8601String(),
                'choices' => $this->question->choices->map(fn ($c) => [
                    'id' => $c->id,
                    'label' => $c->label,
                    'display_order' => $c->display_order,
                ]),
            ],
        ];
    }
}
