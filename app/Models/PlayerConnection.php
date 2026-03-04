<?php

namespace App\Models;

use App\Enums\ConnectionEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerConnection extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'session_player_id',
        'session_id',
        'event',
        'ip_address',
        'user_agent',
        'browser_fingerprint',
        'occurred_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => ConnectionEvent::class,
            'occurred_at' => 'datetime:Y-m-d H:i:s.v',
            'created_at' => 'datetime',
        ];
    }

    public function sessionPlayer(): BelongsTo
    {
        return $this->belongsTo(SessionPlayer::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }
}
