<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiChatEvaluation extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'message_id',
        'groundedness_score',
        'relevance_score',
        'has_hallucination_risk',
        'verdict',
        'risk_flags',
        'evaluated_at',
    ];

    protected function casts(): array
    {
        return [
            'groundedness_score' => 'decimal:3',
            'relevance_score' => 'decimal:3',
            'has_hallucination_risk' => 'boolean',
            'risk_flags' => 'array',
            'evaluated_at' => 'datetime',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(AiChatMessage::class, 'message_id');
    }
}
