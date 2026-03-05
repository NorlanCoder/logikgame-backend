<?php

namespace App\Models;

use App\Enums\FinaleScenario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinalResult extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'session_id',
        'session_player_id',
        'finale_scenario',
        'final_gain',
        'is_winner',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'finale_scenario' => FinaleScenario::class,
            'final_gain' => 'integer',
            'is_winner' => 'boolean',
            'position' => 'integer',
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
}
