<?php

namespace App\Models;

use App\Enums\ActorType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionEvent extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'session_id',
        'event_type',
        'actor_type',
        'actor_id',
        'session_round_id',
        'question_id',
        'payload',
        'occurred_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'actor_type' => ActorType::class,
            'payload' => 'array',
            'occurred_at' => 'datetime:Y-m-d H:i:s.v',
            'created_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function sessionRound(): BelongsTo
    {
        return $this->belongsTo(SessionRound::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
