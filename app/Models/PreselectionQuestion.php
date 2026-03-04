<?php

namespace App\Models;

use App\Enums\AnswerType;
use App\Enums\MediaType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PreselectionQuestion extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'session_id',
        'text',
        'media_type',
        'media_url',
        'answer_type',
        'correct_answer',
        'number_is_decimal',
        'duration',
        'display_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'media_type' => MediaType::class,
            'answer_type' => AnswerType::class,
            'number_is_decimal' => 'boolean',
            'duration' => 'integer',
            'display_order' => 'integer',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function choices(): HasMany
    {
        return $this->hasMany(PreselectionQuestionChoice::class)->orderBy('display_order');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(PreselectionAnswer::class);
    }
}
