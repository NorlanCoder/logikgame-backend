<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Round6TurnOrder extends Model
{
    protected $table = 'round6_turn_order';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'session_round_id',
        'session_player_id',
        'turn_order',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'turn_order' => 'integer',
            'is_active' => 'boolean',
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
