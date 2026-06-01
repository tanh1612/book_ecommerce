<?php

namespace App\Console\Commands\Ai;

use App\Models\Book;
use App\Services\Ai\BookRagSyncService;
use App\Services\Ai\MeilisearchRagDocumentWriter;
use App\Services\Ai\QueueBookRagSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncBookRagDocumentsCommand extends Command
{
    protected $signature = 'ai:sync-book-rag-documents
                            {--book-id= : Sync a single book by id}
                            {--pending : Process Redis pending book ids only}
                            {--all : Sync all active books}
                            {--from-id= : Resume full sync from this book id}
                            {--limit= : Maximum number of books to sync in this run}
                            {--missing-vectors : Only sync books missing Meilisearch vectors}
                            {--chunk= : Override sync batch size between Gemini calls}
                            {--dry-run : List target books without calling Gemini}';

    protected $description = 'Embed and sync book RAG documents with vectors into Meilisearch';

    public function handle(
        BookRagSyncService $bookRagSyncService,
        QueueBookRagSyncService $queueBookRagSyncService,
        MeilisearchRagDocumentWriter $documentWriter,
    ): int {
        if (! $this->option('dry-run') && blank(config('ai.gemini.api_key'))) {
            $this->error('GEMINI_API_KEY is missing. Set it in .env before running RAG sync.');

            return self::FAILURE;
        }

        if ($this->option('book-id') !== null) {
            return $this->syncSingleBook($bookRagSyncService, (int) $this->option('book-id'));
        }

        if ($this->option('pending')) {
            return $this->syncPending($bookRagSyncService, $queueBookRagSyncService);
        }

        if ($this->option('all') || (! $this->option('pending') && $this->option('book-id') === null)) {
            return $this->syncAllActiveBooks($bookRagSyncService, $documentWriter);
        }

        $this->error('No sync mode selected.');

        return self::FAILURE;
    }

    private function syncSingleBook(BookRagSyncService $bookRagSyncService, int $bookId): int
    {
        if ($bookId <= 0) {
            $this->error('Invalid --book-id value.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info("Dry run — would sync book_id [{$bookId}].");

            return self::SUCCESS;
        }

        try {
            if (! $bookRagSyncService->sync($bookId)) {
                $this->warn("Book [{$bookId}] was not found or skipped.");

                return self::FAILURE;
            }
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Book [{$bookId}] RAG document synced.");

        return self::SUCCESS;
    }

    private function syncPending(
        BookRagSyncService $bookRagSyncService,
        QueueBookRagSyncService $queueBookRagSyncService,
    ): int {
        $batchSize = $this->syncBatchSize();

        if ($this->option('dry-run')) {
            $this->info('Dry run — pending count: '.$queueBookRagSyncService->pendingCount());

            return self::SUCCESS;
        }

        $successCount = 0;
        $skippedCount = 0;
        $failureCount = 0;
        $rateLimited = false;

        while (($bookIds = $queueBookRagSyncService->claimBatch($batchSize)) !== []) {
            $result = $this->processBatch($bookRagSyncService, $bookIds);
            $finalized = $queueBookRagSyncService->finalizeBatchResult($bookIds, $result);

            $successCount += count($result['success']);
            $skippedCount += count($result['skipped']);
            $failureCount += count($result['failed']) + count($finalized['dead_letter']);

            if ($result['rate_limited']) {
                $rateLimited = true;
                $this->warn('Gemini rate limit reached. Unprocessed ids were requeued for later retry.');

                break;
            }

            if ($queueBookRagSyncService->pendingCount() === 0) {
                break;
            }

            $delaySeconds = $finalized['dispatch_delay_seconds'];

            if ($delaySeconds > 0) {
                sleep($delaySeconds);
            } else {
                $this->sleepBetweenBatches();
            }
        }

        $this->info("Pending RAG sync finished. success={$successCount}, skipped={$skippedCount}, failed={$failureCount}.");

        if ($rateLimited || $failureCount > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function syncAllActiveBooks(
        BookRagSyncService $bookRagSyncService,
        MeilisearchRagDocumentWriter $documentWriter,
    ): int {
        $batchSize = $this->syncBatchSize();
        $sleepMs = max(0, (int) config('ai.rag.sync_batch_sleep_ms', 500));
        $bookIds = $this->resolveTargetBookIds($documentWriter);

        if ($this->option('dry-run')) {
            $this->info('Dry run — would sync '.count($bookIds).' active book(s).');

            return self::SUCCESS;
        }

        if ($bookIds === []) {
            $this->info('No books matched the sync criteria.');

            return self::SUCCESS;
        }

        $successCount = 0;
        $failureCount = 0;
        $rateLimited = false;
        $chunks = array_chunk($bookIds, $batchSize);

        $bar = $this->output->createProgressBar(count($bookIds));
        $bar->start();

        foreach ($chunks as $index => $chunk) {
            $result = $this->processBatch($bookRagSyncService, $chunk);

            $successCount += count($result['success']);
            $failureCount += count($result['failed']);
            $bar->advance(count($chunk));

            if ($result['rate_limited']) {
                $rateLimited = true;

                $this->newLine();
                $this->warn('Gemini rate limit reached. Stop early to avoid cascading failures.');
                $this->line('Resume with --from-id, --missing-vectors, or --pending after quota resets.');

                if ($result['unprocessed'] !== []) {
                    $nextFromId = min($result['unprocessed']);
                    $this->line("Suggested resume: php artisan ai:sync-book-rag-documents --all --from-id={$nextFromId}");
                }

                break;
            }

            if ($index < count($chunks) - 1 && $sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Full RAG sync finished. success={$successCount}, failed={$failureCount}.");

        if ($rateLimited || $failureCount > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return list<int>
     */
    private function resolveTargetBookIds(MeilisearchRagDocumentWriter $documentWriter): array
    {
        $fromId = $this->option('from-id') !== null ? (int) $this->option('from-id') : null;
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        if ($this->option('missing-vectors')) {
            return $this->resolveMissingVectorBookIds($documentWriter, $fromId, $limit);
        }

        $query = Book::query()->active()->orderBy('id');

        if ($fromId !== null && $fromId > 0) {
            $query->where('id', '>=', $fromId);
        }

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        return $query->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
    }

    /**
     * @return list<int>
     */
    private function resolveMissingVectorBookIds(
        MeilisearchRagDocumentWriter $documentWriter,
        ?int $fromId,
        ?int $limit,
    ): array {
        $query = Book::query()->active()->orderBy('id');

        if ($fromId !== null && $fromId > 0) {
            $query->where('id', '>=', $fromId);
        }

        $missingIds = [];

        foreach ($query->cursor() as $book) {
            if ($documentWriter->getDocumentVectors((int) $book->id) !== null) {
                continue;
            }

            $missingIds[] = (int) $book->id;

            if ($limit !== null && $limit > 0 && count($missingIds) >= $limit) {
                break;
            }
        }

        return $missingIds;
    }

    /**
     * @param  list<int>  $bookIds
     * @return array{success: list<int>, failed: list<int>, unprocessed: list<int>, skipped: list<int>, rate_limited: bool}
     */
    private function processBatch(BookRagSyncService $bookRagSyncService, array $bookIds): array
    {
        try {
            return $bookRagSyncService->syncMany($bookIds);
        } catch (Throwable $e) {
            $this->newLine();
            $this->warn('Batch failed: '.$e->getMessage());

            return [
                'success' => [],
                'failed' => $bookIds,
                'unprocessed' => [],
                'skipped' => [],
                'rate_limited' => false,
            ];
        }
    }

    private function syncBatchSize(): int
    {
        return max(1, (int) ($this->option('chunk') ?: config('ai.rag.sync_batch_size', 20)));
    }

    private function sleepBetweenBatches(): void
    {
        $sleepMs = max(0, (int) config('ai.rag.sync_batch_sleep_ms', 500));

        if ($sleepMs > 0) {
            usleep($sleepMs * 1000);
        }
    }
}
