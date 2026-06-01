<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AiChatMessage extends Model
{
    protected $fillable = [
        'session_id',
        'account_id',
        'question',
        'answer',
        'model_version',
        'retrieval_strategy',
        'retrieval_top_score',
        'retrieval_matched',
        'retrieved_books',
        'token_usage',
        'latency_ms',
        'error_code',
    ];

    protected function casts(): array
    {
        return [
            'retrieval_matched' => 'boolean',
            'retrieval_top_score' => 'decimal:4',
            'retrieved_books' => 'array',
            'token_usage' => 'array',
            'latency_ms' => 'integer',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function evaluation(): HasOne
    {
        return $this->hasOne(AiChatEvaluation::class, 'message_id');
    }

    public function feedback(): HasOne
    {
        return $this->hasOne(AiChatFeedback::class, 'message_id');
    }
}
