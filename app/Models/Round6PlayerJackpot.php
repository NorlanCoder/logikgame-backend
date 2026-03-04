<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Round6PlayerJackpot extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'session_round_id',
        'session_player_id',
        'bonus_count',
        'personal_jackpot',
        'departed_with',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bonus_count' => 'integer',
            'personal_jackpot' => 'integer',
            'departed_with' => 'integer',
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
