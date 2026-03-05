<?php

namespace App\Models;

use App\Enums\RegistrationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Registration extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'session_id',
        'player_id',
        'status',
        'confirmation_email_sent_at',
        'selection_email_sent_at',
        'rejection_email_sent_at',
        'registered_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RegistrationStatus::class,
            'confirmation_email_sent_at' => 'datetime',
            'selection_email_sent_at' => 'datetime',
            'rejection_email_sent_at' => 'datetime',
            'registered_at' => 'datetime',
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

    public function preselectionResult(): HasOne
    {
        return $this->hasOne(PreselectionResult::class);
    }

    public function preselectionAnswers(): HasMany
    {
        return $this->hasMany(PreselectionAnswer::class);
    }

    public function sessionPlayer(): HasOne
    {
        return $this->hasOne(SessionPlayer::class);
    }
}
