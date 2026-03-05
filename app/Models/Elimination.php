<?php

namespace App\Models;

use App\Enums\EliminationReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Elimination extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'session_player_id',
        'session_round_id',
        'question_id',
        'reason',
        'capital_transferred',
        'eliminated_at',
        'is_manual',
        'admin_note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => EliminationReason::class,
            'capital_transferred' => 'integer',
            'eliminated_at' => 'datetime',
            'is_manual' => 'boolean',
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
}
