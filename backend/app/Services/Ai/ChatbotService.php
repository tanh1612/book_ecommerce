<?php

namespace App\Services\Ai;

use App\Exceptions\Ai\GeminiClientException;
use App\Models\AiChatMessage;
use App\Services\Ai\Dto\BookRagRetrievalResult;
use App\Services\Ai\Dto\GeminiGenerateContentRequest;
use App\Services\Ai\Dto\RetrievedBookPromptContext;
use Illuminate\Support\Facades\Log;
use Throwable;

class ChatbotService
{
    public function __construct(
        private readonly ChatHistoryStore $chatHistoryStore,
        private readonly BookRagRetriever $bookRagRetriever,
        private readonly RetrievedBookContextLoader $retrievedBookContextLoader,
        private readonly PromptBuilder $promptBuilder,
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
        $history = $this->chatHistoryStore->getRecentTurns($sessionId);

        try {
            $retrieval = $this->bookRagRetriever->retrieve($question);
        } catch (GeminiClientException $e) {
            Log::warning('Gemini embedding failed during retrieval, returning fallback', [
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
                retrieval: null,
                bookContexts: [],
                effectiveMatched: false,
                errorCode: 'embedding_failed',
            );
        }

        $bookContexts = $retrieval->matched
            ? $this->retrievedBookContextLoader->load($retrieval->documents)
            : [];

        $effectiveMatched = $retrieval->matched && $bookContexts !== [];

        $prompt = $this->promptBuilder->build(
            question: $question,
            history: $history,
            retrieval: $retrieval,
            bookContexts: $bookContexts,
        );

        try {
            $chatResult = $this->geminiClient->generateAnswer(new GeminiGenerateContentRequest(
                userText: $prompt->userText,
                systemInstruction: $prompt->systemInstruction,
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
                retrieval: $retrieval,
                bookContexts: $bookContexts,
                effectiveMatched: $effectiveMatched,
                errorCode: null,
            );

            return $this->buildSuccessResponse(
                sessionId: $sessionId,
                answer: $chatResult->text,
                model: $chatResult->model,
                retrieval: $retrieval,
                bookContexts: $bookContexts,
                effectiveMatched: $effectiveMatched,
                messageId: $message?->id,
            );
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
                retrieval: $retrieval,
                bookContexts: $bookContexts,
                effectiveMatched: $effectiveMatched,
                errorCode: 'gemini_chat_failed',
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
    private function buildSuccessResponse(
        string $sessionId,
        string $answer,
        string $model,
        BookRagRetrievalResult $retrieval,
        array $bookContexts,
        bool $effectiveMatched,
        ?int $messageId,
    ): array {
        return [
            'message_id' => $messageId,
            'answer' => $answer,
            'sources' => $this->mapSources($bookContexts, $effectiveMatched),
            'meta' => [
                'session_id' => $sessionId,
                'model' => $model,
                'retrieval' => $this->mapRetrievalMeta($retrieval, $effectiveMatched),
                'evaluation' => null,
            ],
        ];
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
        ?BookRagRetrievalResult $retrieval,
        array $bookContexts,
        bool $effectiveMatched,
        string $errorCode,
    ): array {
        $answer = (string) config('ai.chat.fallback_message');
        $retrieval ??= $this->emptyRetrievalResult();

        $message = $this->logMessage(
            sessionId: $sessionId,
            accountId: $accountId,
            question: $question,
            answer: $answer,
            modelVersion: (string) config('ai.gemini.chat_model'),
            latencyMs: $this->elapsedMilliseconds($startedAt),
            tokenUsage: null,
            retrieval: $retrieval,
            bookContexts: $bookContexts,
            effectiveMatched: $effectiveMatched,
            errorCode: $errorCode,
        );

        return [
            'message_id' => $message?->id,
            'answer' => $answer,
            'sources' => [],
            'meta' => [
                'session_id' => $sessionId,
                'model' => null,
                'retrieval' => $this->mapRetrievalMeta($retrieval, $effectiveMatched),
                'evaluation' => null,
                'error_code' => $errorCode,
            ],
        ];
    }

    /**
     * @param  list<RetrievedBookPromptContext>  $bookContexts
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
        BookRagRetrievalResult $retrieval,
        array $bookContexts,
        bool $effectiveMatched,
        ?string $errorCode,
    ): ?AiChatMessage {
        try {
            return AiChatMessage::query()->create([
                'session_id' => $sessionId,
                'account_id' => $accountId,
                'question' => $question,
                'answer' => $answer,
                'model_version' => $modelVersion,
                'retrieval_strategy' => $retrieval->strategy,
                'retrieval_top_score' => $retrieval->topScore,
                'retrieval_matched' => $effectiveMatched,
                'retrieved_books' => $this->mapRetrievedBooksForLog($bookContexts, $effectiveMatched),
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

    /**
     * @param  list<RetrievedBookPromptContext>  $bookContexts
     * @return list<array{book_id: int, score: float}>
     */
    private function mapRetrievedBooksForLog(array $bookContexts, bool $effectiveMatched): ?array
    {
        if (! $effectiveMatched || $bookContexts === []) {
            return null;
        }

        return array_map(
            static fn (RetrievedBookPromptContext $context): array => [
                'book_id' => $context->bookId,
                'score' => $context->similarityScore,
            ],
            $bookContexts,
        );
    }

    /**
     * @param  list<RetrievedBookPromptContext>  $bookContexts
     * @return list<array{book_id: int, name: string, slug: string, score: float}>
     */
    private function mapSources(array $bookContexts, bool $effectiveMatched): array
    {
        if (! $effectiveMatched || $bookContexts === []) {
            return [];
        }

        return array_map(
            static fn (RetrievedBookPromptContext $context): array => [
                'book_id' => $context->bookId,
                'name' => $context->name,
                'slug' => $context->slug,
                'score' => $context->similarityScore,
            ],
            $bookContexts,
        );
    }

    /**
     * @return array{strategy: string, top_score: float|null, matched: bool}
     */
    private function mapRetrievalMeta(BookRagRetrievalResult $retrieval, bool $effectiveMatched): array
    {
        return [
            'strategy' => $retrieval->strategy,
            'top_score' => $retrieval->topScore,
            'matched' => $effectiveMatched,
        ];
    }

    private function emptyRetrievalResult(): BookRagRetrievalResult
    {
        return new BookRagRetrievalResult(
            matched: false,
            topScore: null,
            documents: [],
            strategy: 'none',
        );
    }

    private function elapsedMilliseconds(int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }
}
