<?php

namespace App\Models;

use App\Enums\HintType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionHint extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'question_id',
        'hint_type',
        'time_penalty_seconds',
        'removed_choice_ids',
        'revealed_letters',
        'range_hint_text',
        'range_min',
        'range_max',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hint_type' => HintType::class,
            'time_penalty_seconds' => 'integer',
            'removed_choice_ids' => 'array',
            'revealed_letters' => 'array',
            'range_min' => 'decimal:4',
            'range_max' => 'decimal:4',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
