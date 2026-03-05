<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoundSkip extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'session_player_id',
        'session_round_id',
        'capital_lost',
        'skipped_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capital_lost' => 'integer',
            'skipped_at' => 'datetime',
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
}
