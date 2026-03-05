<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreselectionResult extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'registration_id',
        'correct_answers_count',
        'total_questions',
        'total_response_time_ms',
        'rank',
        'is_selected',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'correct_answers_count' => 'integer',
            'total_questions' => 'integer',
            'total_response_time_ms' => 'integer',
            'rank' => 'integer',
            'is_selected' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }
}
