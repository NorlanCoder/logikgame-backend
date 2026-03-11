<?php

namespace App\Models;

use App\Enums\SessionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Session extends Model
{
    use HasFactory;

    protected $table = 'game_sessions';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'admin_id',
        'name',
        'description',
        'cover_image_url',
        'scheduled_at',
        'max_players',
        'status',
        'registration_opens_at',
        'registration_closes_at',
        'preselection_opens_at',
        'preselection_closes_at',
        'current_round_id',
        'current_question_id',
        'jackpot',
        'players_remaining',
        'reconnection_delay',
        'started_at',
        'ended_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SessionStatus::class,
            'scheduled_at' => 'datetime',
            'registration_opens_at' => 'datetime',
            'registration_closes_at' => 'datetime',
            'preselection_opens_at' => 'datetime',
            'preselection_closes_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'max_players' => 'integer',
            'jackpot' => 'integer',
            'players_remaining' => 'integer',
            'reconnection_delay' => 'integer',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function currentRound(): BelongsTo
    {
        return $this->belongsTo(SessionRound::class, 'current_round_id');
    }

    public function currentQuestion(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'current_question_id');
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(SessionRound::class)->orderBy('display_order');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function preselectionQuestions(): HasMany
    {
        return $this->hasMany(PreselectionQuestion::class)->orderBy('display_order');
    }

    public function sessionPlayers(): HasMany
    {
        return $this->hasMany(SessionPlayer::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(SessionEvent::class);
    }

    public function jackpotTransactions(): HasMany
    {
        return $this->hasMany(JackpotTransaction::class);
    }

    public function finaleChoices(): HasMany
    {
        return $this->hasMany(FinaleChoice::class);
    }

    public function finalResults(): HasMany
    {
        return $this->hasMany(FinalResult::class);
    }

    public function projectionAccesses(): HasMany
    {
        return $this->hasMany(ProjectionAccess::class);
    }
}
