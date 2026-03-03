<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerAnswer extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'session_player_id',
        'question_id',
        'is_second_chance',
        'second_chance_question_id',
        'answer_value',
        'selected_choice_id',
        'selected_sc_choice_id',
        'is_correct',
        'hint_used',
        'response_time_ms',
        'submitted_at',
        'is_timeout',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_second_chance' => 'boolean',
            'is_correct' => 'boolean',
            'hint_used' => 'boolean',
            'is_timeout' => 'boolean',
            'response_time_ms' => 'integer',
            'submitted_at' => 'datetime:Y-m-d H:i:s.v',
        ];
    }

    public function sessionPlayer(): BelongsTo
    {
        return $this->belongsTo(SessionPlayer::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function secondChanceQuestion(): BelongsTo
    {
        return $this->belongsTo(SecondChanceQuestion::class);
    }

    public function selectedChoice(): BelongsTo
    {
        return $this->belongsTo(QuestionChoice::class, 'selected_choice_id');
    }

    public function selectedScChoice(): BelongsTo
    {
        return $this->belongsTo(SecondChanceQuestionChoice::class, 'selected_sc_choice_id');
    }
}
