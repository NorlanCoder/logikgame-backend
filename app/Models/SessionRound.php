<?php

namespace App\Models;

use App\Enums\RoundStatus;
use App\Enums\RoundType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SessionRound extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'session_id',
        'round_number',
        'round_type',
        'name',
        'is_active',
        'status',
        'display_order',
        'rules_description',
        'started_at',
        'ended_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'round_type' => RoundType::class,
            'status' => RoundStatus::class,
            'is_active' => 'boolean',
            'round_number' => 'integer',
            'display_order' => 'integer',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('display_order');
    }

    public function eliminations(): HasMany
    {
        return $this->hasMany(Elimination::class);
    }

    public function roundRankings(): HasMany
    {
        return $this->hasMany(RoundRanking::class);
    }

    public function turnOrders(): HasMany
    {
        return $this->hasMany(Round6TurnOrder::class);
    }

    public function playerJackpots(): HasMany
    {
        return $this->hasMany(Round6PlayerJackpot::class);
    }
}
