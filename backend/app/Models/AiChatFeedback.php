<?php

namespace App\Models;

use App\Enums\Ai\ChatFeedbackRating;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiChatFeedback extends Model
{
    protected $fillable = [
        'message_id',
        'session_id',
        'account_id',
        'rating',
    ];

    protected function casts(): array
    {
        return [
            'rating' => ChatFeedbackRating::class,
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(AiChatMessage::class, 'message_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
