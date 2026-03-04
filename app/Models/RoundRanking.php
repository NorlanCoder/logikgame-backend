<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoundRanking extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'session_round_id',
        'session_player_id',
        'correct_answers_count',
        'total_response_time_ms',
        'rank',
        'is_qualified',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'correct_answers_count' => 'integer',
            'total_response_time_ms' => 'integer',
            'rank' => 'integer',
            'is_qualified' => 'boolean',
        ];
    }

    public function sessionRound(): BelongsTo
    {
        return $this->belongsTo(SessionRound::class);
    }

    public function sessionPlayer(): BelongsTo
    {
        return $this->belongsTo(SessionPlayer::class);
    }
}
