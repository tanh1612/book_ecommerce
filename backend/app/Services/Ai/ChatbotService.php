<?php

namespace App\Services\Ai;

use App\Models\AiChatMessage;
use Illuminate\Support\Facades\Log;
use Throwable;

class ChatbotService
{
    public function __construct(
        private readonly ChatHistoryStore $chatHistoryStore,
    ) {}

    /**
     * @return array{
     *     message_id: int|null,
     *     answer: string,
     *     sources: array<int, mixed>,
     *     meta: array<string, mixed>
     * }
     */
    public function handleStub(string $sessionId, string $question, ?int $accountId = null): array
    {
        $startedAt = hrtime(true);
        $answer = (string) config('ai.chat.stub_message');

        // Lát 2: persist stub exchanges to validate the history pipeline.
        // Lát 7+: only append when Gemini returns a real answer (not fallback/stub).
        $this->chatHistoryStore->appendExchange($sessionId, $question, $answer);

        $message = $this->logMessage(
            sessionId: $sessionId,
            accountId: $accountId,
            question: $question,
            answer: $answer,
            latencyMs: $this->elapsedMilliseconds($startedAt),
        );

        return [
            'message_id' => $message?->id,
            'answer' => $answer,
            'sources' => [],
            'meta' => [
                'session_id' => $sessionId,
                'model' => config('ai.gemini.chat_model'),
                'retrieval' => [
                    'strategy' => 'none',
                    'top_score' => null,
                    'matched' => false,
                ],
                'evaluation' => null,
            ],
        ];
    }

    private function logMessage(
        string $sessionId,
        ?int $accountId,
        string $question,
        string $answer,
        int $latencyMs,
    ): ?AiChatMessage {
        try {
            return AiChatMessage::query()->create([
                'session_id' => $sessionId,
                'account_id' => $accountId,
                'question' => $question,
                'answer' => $answer,
                'model_version' => (string) config('ai.gemini.chat_model'),
                'retrieval_strategy' => 'none',
                'retrieval_top_score' => null,
                'retrieval_matched' => false,
                'retrieved_books' => null,
                'token_usage' => null,
                'latency_ms' => $latencyMs,
                'error_code' => null,
            ]);
        } catch (Throwable $e) {
            Log::error('AI chat message log failed', [
                'session_id' => $sessionId,
                'account_id' => $accountId,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return null;
        }
    }

    private function elapsedMilliseconds(int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }
}
