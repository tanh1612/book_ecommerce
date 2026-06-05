<?php

namespace App\Services\Ai;

use App\Exceptions\Ai\GeminiClientException;
use App\Models\AiChatEvaluation;
use App\Models\AiChatMessage;
use App\Services\Ai\Dto\BookRagRetrievalResult;
use App\Services\Ai\Dto\BookRagRetrievedDocument;
use App\Services\Ai\Dto\ChatEvaluationResult;
use App\Services\Ai\Dto\GeminiGenerateContentRequest;
use App\Services\Ai\Dto\ChatIntentRouteResult;
use App\Services\Ai\Dto\RetrievedBookPromptContext;
use Illuminate\Support\Facades\Log;
use Throwable;

class ChatbotService
{
    public function __construct(
        private readonly ChatHistoryStore $chatHistoryStore,
        private readonly ChatContextStore $chatContextStore,
        private readonly FollowUpQueryResolver $followUpQueryResolver,
        private readonly ExactBookQueryResolver $exactBookQueryResolver,
        private readonly ChatIntentRouter $chatIntentRouter,
        private readonly BookRagRetriever $bookRagRetriever,
        private readonly RetrievedBookContextLoader $retrievedBookContextLoader,
        private readonly AnswerSourceSelector $answerSourceSelector,
        private readonly PromptBuilder $promptBuilder,
        private readonly GeminiClient $geminiClient,
        private readonly ChatEvaluationService $chatEvaluationService,
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

        $intentRoute = $this->chatIntentRouter->route($question);
        if ($intentRoute->shouldShortCircuit) {
            return $this->buildIntentRouteResponse(
                sessionId: $sessionId,
                accountId: $accountId,
                question: $question,
                startedAt: $startedAt,
                intentRoute: $intentRoute,
            );
        }

        $history = $this->chatHistoryStore->getRecentTurns($sessionId);
        $lastSources = $this->chatContextStore->getLastSources($sessionId);
        $currentSource = $this->chatContextStore->getCurrentSource($sessionId);
        $followUpSource = $this->followUpQueryResolver->resolveSource($question, $lastSources, $currentSource);
        $followUpQuery = $this->followUpQueryResolver->resolve($question, $lastSources, $currentSource);

        if ($followUpQuery === null && $this->followUpQueryResolver->isFollowUpQuestion($question)) {
            return $this->buildUnresolvedFollowUpResponse(
                sessionId: $sessionId,
                accountId: $accountId,
                question: $question,
                startedAt: $startedAt,
            );
        }

        $retrievalQuery = $followUpQuery ?? $question;

        $exactDocuments = $followUpSource !== null
            ? [$this->documentFromFollowUpSource($followUpSource)]
            : $this->exactBookQueryResolver->resolveToDocuments($retrievalQuery);

        $requiresExactMatch = $followUpSource === null
            && $exactDocuments === []
            && $this->exactBookQueryResolver->requiresExactMatch($retrievalQuery);

        if ($followUpSource !== null) {
            $retrieval = $this->followUpRetrievalResult();
        } elseif ($requiresExactMatch) {
            $retrieval = $this->emptyRetrievalResult();
        } else {
            try {
                $retrieval = $this->bookRagRetriever->retrieve($retrievalQuery);
            } catch (GeminiClientException $e) {
                Log::warning('Gemini embedding failed during retrieval', [
                    'session_id' => $sessionId,
                    'account_id' => $accountId,
                    'error_code' => $e->errorCode,
                    'http_status' => $e->httpStatus,
                    'latency_ms' => $e->latencyMs,
                ]);

                if ($exactDocuments === []) {
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

                $retrieval = $this->emptyRetrievalResult();
            }
        }

        $hasExactDocuments = $exactDocuments !== [];
        $ragDocuments = $retrieval->matched ? $retrieval->documents : [];
        $documents = $this->selectDocumentsForPrompt($exactDocuments, $ragDocuments);
        $retrieval = $this->withMergedDocuments($retrieval, $documents, $hasExactDocuments);

        $bookContexts = $documents !== []
            ? $this->retrievedBookContextLoader->load($documents)
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

            $citedContexts = $this->answerSourceSelector->select(
                answer: $chatResult->text,
                bookContexts: $bookContexts,
                effectiveMatched: $effectiveMatched,
            );

            $conversationReferents = $citedContexts !== []
                ? $citedContexts
                : $this->resolveConversationReferents($bookContexts, $exactDocuments);

            $message = $this->logMessage(
                sessionId: $sessionId,
                accountId: $accountId,
                question: $question,
                answer: $chatResult->text,
                modelVersion: $chatResult->model,
                latencyMs: $this->elapsedMilliseconds($startedAt),
                tokenUsage: $chatResult->tokenUsage,
                retrieval: $retrieval,
                bookContexts: $conversationReferents,
                effectiveMatched: $effectiveMatched,
                errorCode: null,
            );

            $evaluationMeta = $this->evaluateAndPersist(
                message: $message,
                question: $question,
                answer: $chatResult->text,
                retrievalMatched: $effectiveMatched,
                bookContexts: $bookContexts,
            );

            $this->rememberConversationReferents(
                sessionId: $sessionId,
                lastSources: $lastSources,
                conversationReferents: $conversationReferents,
                keepLastSources: $followUpSource !== null,
            );

            return $this->buildSuccessResponse(
                sessionId: $sessionId,
                answer: $chatResult->text,
                model: $chatResult->model,
                retrieval: $retrieval,
                citedContexts: $citedContexts,
                effectiveMatched: $effectiveMatched,
                messageId: $message?->id,
                evaluation: $evaluationMeta,
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
        array $citedContexts,
        bool $effectiveMatched,
        ?int $messageId,
        ?array $evaluation = null,
    ): array {
        return [
            'message_id' => $messageId,
            'answer' => $answer,
            'sources' => $this->mapSources($citedContexts, $effectiveMatched),
            'meta' => [
                'session_id' => $sessionId,
                'model' => $model,
                'retrieval' => $this->mapRetrievalMeta($retrieval, $effectiveMatched),
                'evaluation' => $evaluation,
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
            bookContexts: [],
            effectiveMatched: false,
            errorCode: $errorCode,
        );

        return [
            'message_id' => $message?->id,
            'answer' => $answer,
            'sources' => [],
            'meta' => [
                'session_id' => $sessionId,
                'model' => null,
                'retrieval' => $this->mapRetrievalMeta($retrieval, false),
                'evaluation' => null,
                'error_code' => $errorCode,
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
    private function buildUnresolvedFollowUpResponse(
        string $sessionId,
        ?int $accountId,
        string $question,
        int $startedAt,
    ): array {
        $answer = 'Mình chưa xác định được cuốn sách bạn đang nhắc tới. Bạn vui lòng gửi lại tên sách hoặc hỏi lại sau khi chatbot vừa gợi ý danh sách sách.';
        $retrieval = $this->emptyRetrievalResult();

        $this->chatContextStore->putLastSources($sessionId, []);
        $this->chatContextStore->putCurrentSource($sessionId, null);

        $message = $this->logMessage(
            sessionId: $sessionId,
            accountId: $accountId,
            question: $question,
            answer: $answer,
            modelVersion: (string) config('ai.gemini.chat_model'),
            latencyMs: $this->elapsedMilliseconds($startedAt),
            tokenUsage: null,
            retrieval: $retrieval,
            bookContexts: [],
            effectiveMatched: false,
            errorCode: null,
        );

        return [
            'message_id' => $message?->id,
            'answer' => $answer,
            'sources' => [],
            'meta' => [
                'session_id' => $sessionId,
                'model' => null,
                'retrieval' => $this->mapRetrievalMeta($retrieval, false),
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
    private function buildIntentRouteResponse(
        string $sessionId,
        ?int $accountId,
        string $question,
        int $startedAt,
        ChatIntentRouteResult $intentRoute,
    ): array {
        $answer = $intentRoute->response
            ?? 'Mình chỉ hỗ trợ tư vấn thông tin và gợi ý sách trên Bookify; mình không xử lý đơn hàng, thanh toán, hoàn tiền hoặc thông tin tài khoản.';
        $retrieval = $this->emptyRetrievalResult();

        $message = $this->logMessage(
            sessionId: $sessionId,
            accountId: $accountId,
            question: $question,
            answer: $answer,
            modelVersion: (string) config('ai.gemini.chat_model'),
            latencyMs: $this->elapsedMilliseconds($startedAt),
            tokenUsage: null,
            retrieval: $retrieval,
            bookContexts: [],
            effectiveMatched: false,
            errorCode: null,
        );

        return [
            'message_id' => $message?->id,
            'answer' => $answer,
            'sources' => [],
            'meta' => [
                'session_id' => $sessionId,
                'model' => null,
                'retrieval' => $this->mapRetrievalMeta($retrieval, false),
                'evaluation' => null,
            ],
        ];
    }

    /**
     * @param  list<BookRagRetrievedDocument>  $exactDocuments
     * @param  list<BookRagRetrievedDocument>  $ragDocuments
     * @return list<BookRagRetrievedDocument>
     */
    private function selectDocumentsForPrompt(array $exactDocuments, array $ragDocuments): array
    {
        if ($exactDocuments !== []) {
            return $exactDocuments;
        }

        $merged = [];
        $seenBookIds = [];

        foreach ($ragDocuments as $document) {
            if (isset($seenBookIds[$document->bookId])) {
                continue;
            }

            $seenBookIds[$document->bookId] = true;
            $merged[] = $document;
        }

        return $merged;
    }

    /**
     * @param  array{book_id: int, name: string, slug: string}  $source
     */
    private function documentFromFollowUpSource(array $source): BookRagRetrievedDocument
    {
        return new BookRagRetrievedDocument(
            bookId: $source['book_id'],
            score: 1.0,
            name: $source['name'],
            slug: $source['slug'],
            raw: ['source' => 'follow_up_context'],
        );
    }

    private function followUpRetrievalResult(): BookRagRetrievalResult
    {
        return new BookRagRetrievalResult(
            matched: false,
            topScore: null,
            documents: [],
            strategy: 'follow_up_context',
        );
    }

    /**
     * @param  list<BookRagRetrievedDocument>  $documents
     */
    private function withMergedDocuments(
        BookRagRetrievalResult $retrieval,
        array $documents,
        bool $hasExactDocuments,
    ): BookRagRetrievalResult {
        $topScore = $documents[0]->score ?? $retrieval->topScore;

        return new BookRagRetrievalResult(
            matched: $retrieval->matched || $hasExactDocuments,
            topScore: $topScore,
            documents: $documents,
            strategy: $retrieval->strategy,
            embeddingLatencyMs: $retrieval->embeddingLatencyMs,
            searchLatencyMs: $retrieval->searchLatencyMs,
        );
    }

    /**
     * API sources only include books named in the answer; follow-up referents may keep a single resolved book.
     *
     * @param  list<RetrievedBookPromptContext>  $bookContexts
     * @param  list<BookRagRetrievedDocument>  $exactDocuments
     * @return list<RetrievedBookPromptContext>
     */
    private function resolveConversationReferents(array $bookContexts, array $exactDocuments): array
    {
        if ($bookContexts === []) {
            return [];
        }

        if (count($exactDocuments) === 1) {
            $exactBookId = $exactDocuments[0]->bookId;

            foreach ($bookContexts as $context) {
                if ($context->bookId === $exactBookId) {
                    return [$context];
                }
            }
        }

        return [];
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
     * @param  list<RetrievedBookPromptContext>  $citedContexts
     * @return list<array{book_id: int, name: string, slug: string}>
     */
    private function mapLastSources(array $citedContexts): array
    {
        return array_map(
            static fn (RetrievedBookPromptContext $context): array => [
                'book_id' => $context->bookId,
                'name' => $context->name,
                'slug' => $context->slug,
            ],
            $citedContexts,
        );
    }

    /**
     * @param  list<array{book_id: int, name: string, slug: string}>  $lastSources
     * @param  list<RetrievedBookPromptContext>  $conversationReferents
     */
    private function rememberConversationReferents(
        string $sessionId,
        array $lastSources,
        array $conversationReferents,
        bool $keepLastSources,
    ): void {
        if ($conversationReferents === []) {
            return;
        }

        $sources = $this->mapLastSources($conversationReferents);

        $this->chatContextStore->putLastSources(
            $sessionId,
            $keepLastSources && $lastSources !== [] ? $lastSources : $sources,
        );

        $this->chatContextStore->putCurrentSource(
            $sessionId,
            count($sources) === 1 ? $sources[0] : null,
        );
    }

    /**
     * @param  list<RetrievedBookPromptContext>  $citedContexts
     * @return list<array{book_id: int, name: string, slug: string, score: float}>
     */
    private function mapSources(array $citedContexts, bool $effectiveMatched): array
    {
        if (! $effectiveMatched || $citedContexts === []) {
            return [];
        }

        return array_map(
            static fn (RetrievedBookPromptContext $context): array => [
                'book_id' => $context->bookId,
                'name' => $context->name,
                'slug' => $context->slug,
                'score' => $context->similarityScore,
            ],
            $citedContexts,
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

    /**
     * @param  list<RetrievedBookPromptContext>  $bookContexts
     * @return array{
     *     verdict: string,
     *     groundedness_score: float,
     *     relevance_score: float,
     *     has_hallucination_risk: bool
     * }|null
     */
    private function evaluateAndPersist(
        ?AiChatMessage $message,
        string $question,
        string $answer,
        bool $retrievalMatched,
        array $bookContexts,
    ): ?array {
        if ($message === null) {
            return null;
        }

        try {
            $result = $this->chatEvaluationService->evaluate(
                question: $question,
                answer: $answer,
                retrievalMatched: $retrievalMatched,
                bookContexts: $bookContexts,
            );

            $this->persistEvaluation($message->id, $result);

            return $this->mapEvaluationMeta($result);
        } catch (Throwable $e) {
            Log::error('AI chat evaluation failed', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return null;
        }
    }

    private function persistEvaluation(int $messageId, ChatEvaluationResult $result): void
    {
        AiChatEvaluation::query()->create([
            'message_id' => $messageId,
            'groundedness_score' => $result->groundednessScore,
            'relevance_score' => $result->relevanceScore,
            'has_hallucination_risk' => $result->hasHallucinationRisk,
            'verdict' => $result->verdict,
            'risk_flags' => $result->riskFlags !== [] ? $result->riskFlags : null,
            'evaluated_at' => now(),
        ]);
    }

    /**
     * @return array{
     *     verdict: string,
     *     groundedness_score: float,
     *     relevance_score: float,
     *     has_hallucination_risk: bool
     * }
     */
    private function mapEvaluationMeta(ChatEvaluationResult $result): array
    {
        return [
            'verdict' => $result->verdict,
            'groundedness_score' => $result->groundednessScore,
            'relevance_score' => $result->relevanceScore,
            'has_hallucination_risk' => $result->hasHallucinationRisk,
        ];
    }

    private function elapsedMilliseconds(int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }
}
