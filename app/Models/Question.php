<?php

namespace App\Models;

use App\Enums\AnswerType;
use App\Enums\MediaType;
use App\Enums\QuestionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Question extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'session_round_id',
        'text',
        'media_type',
        'media_url',
        'answer_type',
        'correct_answer',
        'number_is_decimal',
        'duration',
        'display_order',
        'status',
        'launched_at',
        'closed_at',
        'revealed_at',
        'assigned_player_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'media_type' => MediaType::class,
            'answer_type' => AnswerType::class,
            'status' => QuestionStatus::class,
            'number_is_decimal' => 'boolean',
            'duration' => 'integer',
            'display_order' => 'integer',
            'launched_at' => 'datetime:Y-m-d H:i:s.v',
            'closed_at' => 'datetime:Y-m-d H:i:s.v',
            'revealed_at' => 'datetime:Y-m-d H:i:s.v',
        ];
    }

    public function sessionRound(): BelongsTo
    {
        return $this->belongsTo(SessionRound::class);
    }

    public function assignedPlayer(): BelongsTo
    {
        return $this->belongsTo(SessionPlayer::class, 'assigned_player_id');
    }

    public function choices(): HasMany
    {
        return $this->hasMany(QuestionChoice::class)->orderBy('display_order');
    }

    public function hint(): HasOne
    {
        return $this->hasOne(QuestionHint::class);
    }

    public function secondChanceQuestion(): HasOne
    {
        return $this->hasOne(SecondChanceQuestion::class, 'main_question_id');
    }

    public function playerAnswers(): HasMany
    {
        return $this->hasMany(PlayerAnswer::class);
    }
}
