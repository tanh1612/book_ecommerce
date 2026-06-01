<?php

namespace App\Services\Ai;

use App\Exceptions\Ai\GeminiClientException;
use App\Models\Book;
use Illuminate\Support\Facades\Log;
use Throwable;

class BookRagSyncService
{
    public function __construct(
        private readonly BookRagDocumentFactory $documentFactory,
        private readonly GeminiClient $geminiClient,
        private readonly MeilisearchRagDocumentWriter $documentWriter,
    ) {}

    public function sync(int $bookId): bool
    {
        if ($bookId <= 0) {
            return false;
        }

        $book = $this->loadBook($bookId);

        if ($book === null) {
            Log::debug('Book RAG sync skipped because book was not found', [
                'book_id' => $bookId,
            ]);

            return false;
        }

        $startedAt = hrtime(true);

        try {
            $embeddingText = $this->documentFactory->buildEmbeddingText($book);
            $embedding = $this->geminiClient->embedText($embeddingText);
            $expectedDimensions = (int) config('ai.rag.embedding_dimensions', 768);

            if (count($embedding->vector) !== $expectedDimensions) {
                throw new \RuntimeException(
                    "Gemini embedding dimension mismatch for book {$bookId}: expected {$expectedDimensions}, got ".count($embedding->vector),
                );
            }

            $document = $this->documentFactory->makeDocument($book, $embedding->vector);
            $this->documentWriter->upsert($document);

            Log::info('Book RAG sync completed', [
                'book_id' => $bookId,
                'embedding_model' => $embedding->model,
                'embedding_dimensions' => $embedding->dimensions,
                'latency_ms' => $this->elapsedMilliseconds($startedAt),
            ]);

            return true;
        } catch (GeminiClientException $e) {
            Log::error('Book RAG sync failed during Gemini embedding', [
                'book_id' => $bookId,
                'error_code' => $e->errorCode,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            throw $e;
        } catch (Throwable $e) {
            Log::error('Book RAG sync failed', [
                'book_id' => $bookId,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            throw $e;
        }
    }

    /**
     * @param  list<int>  $bookIds
     * @return array{success: list<int>, failed: list<int>, unprocessed: list<int>, skipped: list<int>, rate_limited: bool}
     */
    public function syncMany(array $bookIds): array
    {
        $normalizedIds = [];

        foreach ($bookIds as $bookId) {
            $bookId = (int) $bookId;

            if ($bookId > 0) {
                $normalizedIds[] = $bookId;
            }
        }

        if ($normalizedIds === []) {
            return $this->emptyBatchResult();
        }

        $startedAt = hrtime(true);
        $books = Book::query()
            ->with([
                'authors:id,name',
                'categories:id,name',
                'detail:book_id,description,language,format,publication_year,num_pages',
                'publisher:id,name',
                'inventories:id,book_id,quantity,reserved_quantity',
            ])
            ->whereIn('id', $normalizedIds)
            ->get()
            ->keyBy('id');

        $entries = [];
        $skipped = [];

        foreach ($normalizedIds as $bookId) {
            $book = $books->get($bookId);

            if ($book === null) {
                $skipped[] = $bookId;

                Log::debug('Book RAG batch sync skipped because book was not found', [
                    'book_id' => $bookId,
                ]);

                continue;
            }

            $entries[] = [
                'book_id' => $bookId,
                'book' => $book,
                'embedding_text' => $this->documentFactory->buildEmbeddingText($book),
            ];
        }

        if ($entries === []) {
            return [
                'success' => [],
                'failed' => [],
                'unprocessed' => [],
                'skipped' => $skipped,
                'rate_limited' => false,
            ];
        }

        $embedBatchSize = max(1, (int) config('ai.rag.embed_batch_size', 25));
        $expectedDimensions = (int) config('ai.rag.embedding_dimensions', 768);
        $stopOnRateLimit = (bool) config('ai.rag.rate_limit_stop_on_429', true);

        $success = [];
        $failed = [];
        $unprocessed = [];
        $rateLimited = false;
        $pendingDocuments = [];

        $entryChunks = array_chunk($entries, $embedBatchSize);

        foreach ($entryChunks as $chunkIndex => $chunk) {
            $texts = array_column($chunk, 'embedding_text');

            try {
                $batchResult = $this->geminiClient->embedTexts($texts);

                if (count($batchResult->vectors) !== count($chunk)) {
                    throw new \RuntimeException(
                        'Gemini batch embedding count mismatch for chunk '.($chunkIndex + 1),
                    );
                }

                foreach ($chunk as $index => $entry) {
                    $vector = $batchResult->vectors[$index];

                    if (count($vector) !== $expectedDimensions) {
                        $failed[] = $entry['book_id'];

                        continue;
                    }

                    $pendingDocuments[] = [
                        'book_id' => $entry['book_id'],
                        'document' => $this->documentFactory->makeDocument($entry['book'], $vector),
                    ];
                }
            } catch (GeminiClientException $e) {
                if ($e->errorCode === GeminiClientException::RATE_LIMIT && $stopOnRateLimit) {
                    $rateLimited = true;

                    for ($remainingIndex = $chunkIndex; $remainingIndex < count($entryChunks); $remainingIndex++) {
                        foreach ($entryChunks[$remainingIndex] as $entry) {
                            $unprocessed[] = $entry['book_id'];
                        }
                    }

                    $unprocessed = array_values(array_unique($unprocessed));

                    break;
                }

                Log::error('Book RAG batch sync failed during Gemini embedding', [
                    'book_ids' => array_column($chunk, 'book_id'),
                    'error_code' => $e->errorCode,
                    'error' => $e->getMessage(),
                    'exception' => $e::class,
                ]);

                foreach ($chunk as $entry) {
                    $failed[] = $entry['book_id'];
                }
            } catch (Throwable $e) {
                Log::error('Book RAG batch sync failed during Gemini embedding', [
                    'book_ids' => array_column($chunk, 'book_id'),
                    'error' => $e->getMessage(),
                    'exception' => $e::class,
                ]);

                foreach ($chunk as $entry) {
                    $failed[] = $entry['book_id'];
                }
            }
        }

        if ($pendingDocuments !== []) {
            try {
                $this->documentWriter->upsertMany(array_column($pendingDocuments, 'document'));
                $success = array_column($pendingDocuments, 'book_id');
            } catch (Throwable $e) {
                Log::error('Book RAG batch sync failed during Meilisearch upsert', [
                    'book_ids' => array_column($pendingDocuments, 'book_id'),
                    'error' => $e->getMessage(),
                    'exception' => $e::class,
                ]);

                $failed = array_values(array_unique(array_merge(
                    $failed,
                    array_column($pendingDocuments, 'book_id'),
                )));
            }
        }

        Log::info('Book RAG batch sync completed', [
            'requested_count' => count($normalizedIds),
            'success_count' => count($success),
            'failed_count' => count($failed),
            'skipped_count' => count($skipped),
            'unprocessed_count' => count($unprocessed),
            'rate_limited' => $rateLimited,
            'latency_ms' => $this->elapsedMilliseconds($startedAt),
        ]);

        return [
            'success' => $success,
            'failed' => array_values(array_unique($failed)),
            'unprocessed' => $unprocessed,
            'skipped' => $skipped,
            'rate_limited' => $rateLimited,
        ];
    }

    /**
     * @return array{success: list<int>, failed: list<int>, unprocessed: list<int>, skipped: list<int>, rate_limited: bool}
     */
    private function emptyBatchResult(): array
    {
        return [
            'success' => [],
            'failed' => [],
            'unprocessed' => [],
            'skipped' => [],
            'rate_limited' => false,
        ];
    }

    private function loadBook(int $bookId): ?Book
    {
        return Book::query()
            ->with([
                'authors:id,name',
                'categories:id,name',
                'detail:book_id,description,language,format,publication_year,num_pages',
                'publisher:id,name',
                'inventories:id,book_id,quantity,reserved_quantity',
            ])
            ->find($bookId);
    }

    private function elapsedMilliseconds(int $startedAt): int
    {
        return (int) ((hrtime(true) - $startedAt) / 1_000_000);
    }
}
