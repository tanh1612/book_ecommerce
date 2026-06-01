<?php

namespace App\Services\Ai;

use App\Services\Ai\Dto\BookRagRetrievalResult;
use App\Services\Ai\Dto\BookRagRetrievedDocument;
use App\Services\Ai\Support\ReciprocalRankFusionMerger;
use Illuminate\Support\Facades\Log;
use Meilisearch\Client;
use Meilisearch\Endpoints\Indexes;
use Throwable;

class BookRagRetriever
{
    public function __construct(
        private readonly GeminiClient $geminiClient,
        private readonly ReciprocalRankFusionMerger $rankFusionMerger,
        private readonly ?Client $client = null,
    ) {}

    public function retrieve(string $question): BookRagRetrievalResult
    {
        $embeddingResult = $this->geminiClient->embedText($question);
        $searchStartedAt = hrtime(true);

        try {
            $searchOutcome = $this->searchHybrid($question, $embeddingResult->vector);

            return $this->buildResult(
                strategy: 'hybrid',
                documents: $searchOutcome['documents'],
                keywordTop1Score: null,
                embeddingLatencyMs: $embeddingResult->latencyMs,
                searchLatencyMs: $this->elapsedMilliseconds($searchStartedAt),
            );
        } catch (Throwable $e) {
            if ($this->isHybridUnsupported($e)) {
                Log::warning('Meilisearch hybrid search unsupported, falling back to RRF', [
                    'question' => $this->truncateQuestion($question),
                    'error' => $e->getMessage(),
                    'exception' => $e::class,
                ]);

                try {
                    $searchOutcome = $this->searchRrf($question, $embeddingResult->vector);

                    return $this->buildResult(
                        strategy: 'rrf',
                        documents: $searchOutcome['documents'],
                        keywordTop1Score: $searchOutcome['keyword_top1_score'],
                        embeddingLatencyMs: $embeddingResult->latencyMs,
                        searchLatencyMs: $this->elapsedMilliseconds($searchStartedAt),
                    );
                } catch (Throwable $rrfException) {
                    Log::warning('Book RAG RRF retrieval failed', [
                        'question' => $this->truncateQuestion($question),
                        'error' => $rrfException->getMessage(),
                        'exception' => $rrfException::class,
                    ]);

                    return $this->emptyResult(
                        embeddingLatencyMs: $embeddingResult->latencyMs,
                        searchLatencyMs: $this->elapsedMilliseconds($searchStartedAt),
                    );
                }
            }

            Log::warning('Book RAG retrieval failed', [
                'question' => $this->truncateQuestion($question),
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return $this->emptyResult(
                embeddingLatencyMs: $embeddingResult->latencyMs,
                searchLatencyMs: $this->elapsedMilliseconds($searchStartedAt),
            );
        }
    }

    /**
     * @param  array<int, float>  $vector
     * @return array{documents: list<BookRagRetrievedDocument>}
     */
    private function searchHybrid(string $question, array $vector): array
    {
        $response = $this->index()->search($question, $this->hybridSearchOptions($vector), ['raw' => true]);

        return [
            'documents' => $this->mapHitsToDocuments($response['hits'] ?? []),
        ];
    }

    /**
     * @param  array<int, float>  $vector
     * @return array{documents: list<BookRagRetrievedDocument>, keyword_top1_score: ?float}
     */
    private function searchRrf(string $question, array $vector): array
    {
        $topK = $this->topK();
        $searchOptions = $this->baseSearchOptions($topK);

        $keywordResponse = $this->index()->search($question, $searchOptions, ['raw' => true]);
        $keywordHits = $keywordResponse['hits'] ?? [];
        $keywordTop1Score = $this->extractRankingScore($keywordHits[0] ?? null);

        $vectorResponse = $this->index()->search('', array_merge($searchOptions, [
            'vector' => $vector,
        ]), ['raw' => true]);
        $vectorHits = $vectorResponse['hits'] ?? [];

        $hitMetadata = $this->collectHitMetadata([...$keywordHits, ...$vectorHits]);
        $keywordIds = $this->extractBookIds($keywordHits);
        $vectorIds = $this->extractBookIds($vectorHits);
        $rrfScores = $this->rankFusionMerger->merge(
            array_filter([$keywordIds, $vectorIds], static fn (array $ids): bool => $ids !== []),
            $this->rrfK(),
        );

        arsort($rrfScores, SORT_NUMERIC);
        $documents = [];

        foreach (array_slice($rrfScores, 0, $topK, true) as $bookId => $score) {
            $metadata = $hitMetadata[$bookId] ?? null;
            if ($metadata === null) {
                continue;
            }

            $documents[] = new BookRagRetrievedDocument(
                bookId: $bookId,
                score: (float) $score,
                name: $metadata['name'],
                slug: $metadata['slug'],
                raw: $metadata['raw'],
            );
        }

        return [
            'documents' => $documents,
            'keyword_top1_score' => $keywordTop1Score,
        ];
    }

    /**
     * @param  list<BookRagRetrievedDocument>  $documents
     */
    private function buildResult(
        string $strategy,
        array $documents,
        ?float $keywordTop1Score,
        int $embeddingLatencyMs,
        int $searchLatencyMs,
    ): BookRagRetrievalResult {
        $topScore = $documents[0]->score ?? null;

        return new BookRagRetrievalResult(
            matched: $this->resolveMatched($strategy, $topScore, $keywordTop1Score, $documents !== []),
            topScore: $topScore,
            documents: $documents,
            strategy: $strategy,
            embeddingLatencyMs: $embeddingLatencyMs,
            searchLatencyMs: $searchLatencyMs,
        );
    }

    private function emptyResult(int $embeddingLatencyMs, int $searchLatencyMs): BookRagRetrievalResult
    {
        return new BookRagRetrievalResult(
            matched: false,
            topScore: null,
            documents: [],
            strategy: 'none',
            embeddingLatencyMs: $embeddingLatencyMs,
            searchLatencyMs: $searchLatencyMs,
        );
    }

    private function resolveMatched(
        string $strategy,
        ?float $topScore,
        ?float $keywordTop1Score,
        bool $hasDocuments,
    ): bool {
        if (! $hasDocuments || $topScore === null) {
            return false;
        }

        if ($strategy === 'hybrid') {
            return $topScore >= (float) config('ai.rag.min_score', 0.65);
        }

        if ($strategy === 'rrf') {
            $rrfMinScore = (float) config('ai.rag.rrf_min_score', 0.016);
            $keywordTop1MinScore = (float) config('ai.rag.keyword_top1_min_score', 0.70);

            return $topScore >= $rrfMinScore
                || ($keywordTop1Score !== null && $keywordTop1Score >= $keywordTop1MinScore);
        }

        return false;
    }

    /**
     * @param  array<int, float>  $vector
     * @return array<string, mixed>
     */
    private function hybridSearchOptions(array $vector): array
    {
        return array_merge($this->baseSearchOptions($this->topK()), [
            'hybrid' => [
                'semanticRatio' => (float) config('ai.rag.hybrid_semantic_ratio', 0.6),
                'embedder' => (string) config('ai.rag.embedder_name'),
            ],
            'vector' => $vector,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function baseSearchOptions(int $limit): array
    {
        return [
            'filter' => 'is_active = true',
            'limit' => $limit,
            'showRankingScore' => true,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $hits
     * @return list<BookRagRetrievedDocument>
     */
    private function mapHitsToDocuments(array $hits): array
    {
        $documents = [];

        foreach ($hits as $hit) {
            $document = $this->mapHitToDocument($hit);
            if ($document !== null) {
                $documents[] = $document;
            }
        }

        return $documents;
    }

    /**
     * @param  array<string, mixed>  $hit
     */
    private function mapHitToDocument(array $hit): ?BookRagRetrievedDocument
    {
        $bookId = isset($hit['id']) ? (int) $hit['id'] : 0;
        $name = isset($hit['name']) ? trim((string) $hit['name']) : '';
        $slug = isset($hit['slug']) ? trim((string) $hit['slug']) : '';

        if ($bookId <= 0 || $name === '') {
            return null;
        }

        return new BookRagRetrievedDocument(
            bookId: $bookId,
            score: $this->extractRankingScore($hit) ?? 0.0,
            name: $name,
            slug: $slug,
            raw: $hit,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $hits
     * @return list<int>
     */
    private function extractBookIds(array $hits): array
    {
        $bookIds = [];

        foreach ($hits as $hit) {
            $bookId = isset($hit['id']) ? (int) $hit['id'] : 0;
            if ($bookId > 0) {
                $bookIds[] = $bookId;
            }
        }

        return $bookIds;
    }

    /**
     * @param  list<array<string, mixed>>  $hits
     * @return array<int, array{name: string, slug: string, raw: array<string, mixed>}>
     */
    private function collectHitMetadata(array $hits): array
    {
        $metadata = [];

        foreach ($hits as $hit) {
            $bookId = isset($hit['id']) ? (int) $hit['id'] : 0;
            $name = isset($hit['name']) ? trim((string) $hit['name']) : '';

            if ($bookId <= 0 || $name === '') {
                continue;
            }

            $metadata[$bookId] = [
                'name' => $name,
                'slug' => isset($hit['slug']) ? trim((string) $hit['slug']) : '',
                'raw' => $hit,
            ];
        }

        return $metadata;
    }

    /**
     * @param  array<string, mixed>|null  $hit
     */
    private function extractRankingScore(?array $hit): ?float
    {
        if ($hit === null || ! array_key_exists('_rankingScore', $hit)) {
            return null;
        }

        return (float) $hit['_rankingScore'];
    }

    private function isHybridUnsupported(Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        $unknownFieldHybrid = str_contains($message, 'unknown field')
            && str_contains($message, 'hybrid');
        $invalidHybrid = str_contains($message, 'invalid')
            && str_contains($message, 'hybrid');
        $unsupportedHybridFeature = str_contains($message, 'unsupported')
            && (str_contains($message, 'hybrid') || str_contains($message, 'semanticratio'));

        return $unknownFieldHybrid || $invalidHybrid || $unsupportedHybridFeature;
    }

    private function index(): Indexes
    {
        $indexName = (string) config('ai.rag.index_name');

        return $this->client()->index($indexName);
    }

    private function client(): Client
    {
        if ($this->client !== null) {
            return $this->client;
        }

        return new Client(
            (string) config('scout.meilisearch.host'),
            config('scout.meilisearch.key'),
        );
    }

    private function topK(): int
    {
        return max(1, (int) config('ai.rag.top_k', 5));
    }

    private function rrfK(): int
    {
        return max(1, (int) config('ai.rag.rrf_k', 60));
    }

    private function truncateQuestion(string $question): string
    {
        return mb_strlen($question) > 120 ? mb_substr($question, 0, 120).'…' : $question;
    }

    private function elapsedMilliseconds(int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }
}
