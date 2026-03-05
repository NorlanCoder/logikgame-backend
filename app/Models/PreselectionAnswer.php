<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreselectionAnswer extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'registration_id',
        'preselection_question_id',
        'answer_value',
        'selected_choice_id',
        'is_correct',
        'response_time_ms',
        'submitted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'response_time_ms' => 'integer',
            'submitted_at' => 'datetime:Y-m-d H:i:s.v',
        ];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function preselectionQuestion(): BelongsTo
    {
        return $this->belongsTo(PreselectionQuestion::class);
    }

    public function selectedChoice(): BelongsTo
    {
        return $this->belongsTo(PreselectionQuestionChoice::class, 'selected_choice_id');
    }
}
