<?php

namespace App\Services\Ai;

use App\Exceptions\Ai\GeminiClientException;
use App\Models\AiChatMessage;
use App\Services\Ai\Dto\GeminiGenerateContentRequest;
use Illuminate\Support\Facades\Log;
use Throwable;

class ChatbotService
{
    private const SYSTEM_INSTRUCTION = 'Ban la tro ly ao cua Bookify. Chi ho tro ve sach va mua sach tren Bookify. '
        .'Tra loi bang tieng Viet, ngan gon, de hieu. '
        .'Neu chua co du lieu sach phu hop, noi ro chua tim thay trong du lieu Bookify.';

    public function __construct(
        private readonly ChatHistoryStore $chatHistoryStore,
        private readonly GeminiClient $geminiClient,
    ) {}

    /**
     * @return array{
     *     message_id: int|null,
     *     answer: string,
     *     sources: array<int, mixed>,
     *     meta: array<string, mixed>
     * }
     */
    public function handle(string $sessionId, string $question, ?int $accountId = null): array
    {
        $startedAt = hrtime(true);

        try {
            $chatResult = $this->geminiClient->generateAnswer(new GeminiGenerateContentRequest(
                userText: $question,
                systemInstruction: self::SYSTEM_INSTRUCTION,
            ));

            $this->chatHistoryStore->appendExchange($sessionId, $question, $chatResult->text);

            $message = $this->logMessage(
                sessionId: $sessionId,
                accountId: $accountId,
                question: $question,
                answer: $chatResult->text,
                modelVersion: $chatResult->model,
                latencyMs: $this->elapsedMilliseconds($startedAt),
                tokenUsage: $chatResult->tokenUsage,
                errorCode: null,
            );

            return [
                'message_id' => $message?->id,
                'answer' => $chatResult->text,
                'sources' => [],
                'meta' => [
                    'session_id' => $sessionId,
                    'model' => $chatResult->model,
                    'retrieval' => [
                        'strategy' => 'none',
                        'top_score' => null,
                        'matched' => false,
                    ],
                    'evaluation' => null,
                ],
            ];
        } catch (GeminiClientException $e) {
            Log::warning('Gemini chat failed, returning fallback', [
                'session_id' => $sessionId,
                'account_id' => $accountId,
                'error_code' => $e->errorCode,
                'http_status' => $e->httpStatus,
                'latency_ms' => $e->latencyMs,
            ]);

            return $this->buildFallbackResponse(
                sessionId: $sessionId,
                accountId: $accountId,
                question: $question,
                startedAt: $startedAt,
            );
        }
    }

    /**
     * @return array{
     *     message_id: int|null,
     *     answer: string,
     *     sources: array<int, mixed>,
     *     meta: array<string, mixed>
     * }
     */
    private function buildFallbackResponse(
        string $sessionId,
        ?int $accountId,
        string $question,
        int $startedAt,
    ): array {
        $answer = (string) config('ai.chat.fallback_message');

        $message = $this->logMessage(
            sessionId: $sessionId,
            accountId: $accountId,
            question: $question,
            answer: $answer,
            modelVersion: (string) config('ai.gemini.chat_model'),
            latencyMs: $this->elapsedMilliseconds($startedAt),
            tokenUsage: null,
            errorCode: 'gemini_chat_failed',
        );

        return [
            'message_id' => $message?->id,
            'answer' => $answer,
            'sources' => [],
            'meta' => [
                'session_id' => $sessionId,
                'model' => null,
                'retrieval' => [
                    'strategy' => 'none',
                    'top_score' => null,
                    'matched' => false,
                ],
                'evaluation' => null,
                'error_code' => 'gemini_chat_failed',
            ],
        ];
    }

    /**
     * @param  array{prompt: int, candidates: int, total: int}|null  $tokenUsage
     */
    private function logMessage(
        string $sessionId,
        ?int $accountId,
        string $question,
        string $answer,
        string $modelVersion,
        int $latencyMs,
        ?array $tokenUsage,
        ?string $errorCode,
    ): ?AiChatMessage {
        try {
            return AiChatMessage::query()->create([
                'session_id' => $sessionId,
                'account_id' => $accountId,
                'question' => $question,
                'answer' => $answer,
                'model_version' => $modelVersion,
                'retrieval_strategy' => 'none',
                'retrieval_top_score' => null,
                'retrieval_matched' => false,
                'retrieved_books' => null,
                'token_usage' => $tokenUsage,
                'latency_ms' => $latencyMs,
                'error_code' => $errorCode,
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
