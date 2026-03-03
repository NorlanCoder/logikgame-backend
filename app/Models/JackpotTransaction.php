<?php

namespace App\Models;

use App\Enums\JackpotTransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JackpotTransaction extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'session_id',
        'session_player_id',
        'session_round_id',
        'transaction_type',
        'amount',
        'jackpot_before',
        'jackpot_after',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'transaction_type' => JackpotTransactionType::class,
            'amount' => 'integer',
            'jackpot_before' => 'integer',
            'jackpot_after' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
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
