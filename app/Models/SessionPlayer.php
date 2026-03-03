<?php

namespace App\Models;

use App\Enums\SessionPlayerStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SessionPlayer extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'session_id',
        'player_id',
        'registration_id',
        'access_token',
        'status',
        'capital',
        'personal_jackpot',
        'final_gain',
        'browser_fingerprint',
        'is_connected',
        'last_connected_at',
        'eliminated_at',
        'elimination_reason',
        'eliminated_in_round_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SessionPlayerStatus::class,
            'capital' => 'integer',
            'personal_jackpot' => 'integer',
            'final_gain' => 'integer',
            'is_connected' => 'boolean',
            'last_connected_at' => 'datetime',
            'eliminated_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function eliminatedInRound(): BelongsTo
    {
        return $this->belongsTo(SessionRound::class, 'eliminated_in_round_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(PlayerAnswer::class);
    }

    public function eliminations(): HasMany
    {
        return $this->hasMany(Elimination::class);
    }

    public function hintUsages(): HasMany
    {
        return $this->hasMany(HintUsage::class);
    }

    public function roundSkips(): HasMany
    {
        return $this->hasMany(RoundSkip::class);
    }

    public function roundRankings(): HasMany
    {
        return $this->hasMany(RoundRanking::class);
    }

    public function connections(): HasMany
    {
        return $this->hasMany(PlayerConnection::class);
    }

    public function finaleChoice(): HasOne
    {
        return $this->hasOne(FinaleChoice::class);
    }

    public function finalResult(): HasOne
    {
        return $this->hasOne(FinalResult::class);
    }
}
