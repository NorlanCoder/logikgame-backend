<?php

namespace App\Models;

use App\Enums\AnswerType;
use App\Enums\MediaType;
use App\Enums\QuestionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SecondChanceQuestion extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'main_question_id',
        'text',
        'media_type',
        'media_url',
        'answer_type',
        'correct_answer',
        'number_is_decimal',
        'duration',
        'status',
        'launched_at',
        'closed_at',
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
            'launched_at' => 'datetime:Y-m-d H:i:s.v',
            'closed_at' => 'datetime:Y-m-d H:i:s.v',
        ];
    }

    public function mainQuestion(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'main_question_id');
    }

    public function choices(): HasMany
    {
        return $this->hasMany(SecondChanceQuestionChoice::class)->orderBy('display_order');
    }
}
