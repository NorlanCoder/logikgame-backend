<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HintUsage extends Model
{
    public $timestamps = false;

    protected $table = 'hint_usages';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'session_player_id',
        'session_round_id',
        'question_id',
        'question_hint_id',
        'time_remaining_before',
        'time_remaining_after',
        'activated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'time_remaining_before' => 'integer',
            'time_remaining_after' => 'integer',
            'activated_at' => 'datetime:Y-m-d H:i:s.v',
            'created_at' => 'datetime',
        ];
    }

    public function sessionPlayer(): BelongsTo
    {
        return $this->belongsTo(SessionPlayer::class);
    }

    public function sessionRound(): BelongsTo
    {
        return $this->belongsTo(SessionRound::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function questionHint(): BelongsTo
    {
        return $this->belongsTo(QuestionHint::class);
    }
}
