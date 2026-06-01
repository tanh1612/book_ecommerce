<?php

namespace App\Services\Ai;

use App\Enums\Ai\ChatFeedbackRating;
use App\Models\AiChatFeedback;
use App\Models\AiChatMessage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;
use Throwable;

class ChatFeedbackService
{
    public function upsert(
        AiChatMessage $message,
        ChatFeedbackRating $rating,
        ?string $sessionId,
        ?int $authenticatedAccountId,
    ): AiChatFeedback {
        if (! $this->canFeedback($message, $sessionId, $authenticatedAccountId)) {
            throw new AuthorizationException('Ban khong co quyen danh gia tin nhan nay.');
        }

        $storedSessionId = $sessionId ?? $message->session_id;

        try {
            return AiChatFeedback::query()->updateOrCreate(
                ['message_id' => $message->id],
                [
                    'session_id' => $storedSessionId,
                    'account_id' => $authenticatedAccountId,
                    'rating' => $rating,
                ],
            );
        } catch (Throwable $e) {
            Log::error('AI chat feedback save failed', [
                'message_id' => $message->id,
                'session_id' => $storedSessionId,
                'account_id' => $authenticatedAccountId,
                'rating' => $rating->value,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            throw $e;
        }
    }

    public function canFeedback(AiChatMessage $message, ?string $sessionId, ?int $authenticatedAccountId): bool
    {
        if ($message->account_id !== null) {
            return $authenticatedAccountId !== null
                && (int) $message->account_id === $authenticatedAccountId;
        }

        return $sessionId !== null && $message->session_id === $sessionId;
    }
}
