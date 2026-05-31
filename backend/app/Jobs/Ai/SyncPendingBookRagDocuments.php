<?php

namespace App\Jobs\Ai;

use App\Services\Ai\BookRagSyncService;
use App\Services\Ai\QueueBookRagSyncService;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncPendingBookRagDocuments implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public int $uniqueFor;

    public function __construct()
    {
        $this->onQueue((string) config('ai.rag.sync_queue', 'ai-rag-sync'));
        $this->uniqueFor = (int) config('ai.rag.sync_job_unique_for', 3600);
    }

    public function handle(
        QueueBookRagSyncService $queueBookRagSyncService,
        BookRagSyncService $bookRagSyncService,
    ): void {
        $batchSize = max(1, (int) config('ai.rag.sync_batch_size', 20));
        $sleepMs = max(0, (int) config('ai.rag.sync_batch_sleep_ms', 500));
        $bookIds = $queueBookRagSyncService->claimBatch($batchSize);

        if ($bookIds === []) {
            return;
        }

        try {
            $result = $bookRagSyncService->syncMany($bookIds);
        } catch (Throwable $e) {
            Log::error('Pending book RAG sync batch failed', [
                'book_ids' => $bookIds,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            $queueBookRagSyncService->requeueMany($bookIds);
            $queueBookRagSyncService->releaseProcessingClaims($bookIds);

            if ($queueBookRagSyncService->pendingCount() > 0) {
                $queueBookRagSyncService->dispatchPendingWorkerIfNeeded(
                    max(0, (int) config('ai.rag.sync_failed_retry_delay_seconds', 60)),
                );
            }

            return;
        }

        $finalized = $queueBookRagSyncService->finalizeBatchResult($bookIds, $result);

        Log::info('Pending book RAG sync batch processed', [
            'batch_size' => count($bookIds),
            'success_count' => count($result['success']),
            'skipped_count' => count($result['skipped']),
            'failed_count' => count($result['failed']),
            'unprocessed_count' => count($result['unprocessed']),
            'requeued_count' => count($finalized['requeued']),
            'dead_letter_count' => count($finalized['dead_letter']),
            'rate_limited' => $result['rate_limited'],
            'remaining_pending' => $queueBookRagSyncService->pendingCount(),
        ]);

        if (! $finalized['should_dispatch']) {
            return;
        }

        if ($finalized['dispatch_delay_seconds'] === 0 && $sleepMs > 0) {
            usleep($sleepMs * 1000);
        }

        $queueBookRagSyncService->dispatchPendingWorkerIfNeeded($finalized['dispatch_delay_seconds']);
    }

    public function uniqueId(): string
    {
        return 'ai-rag-sync:pending-worker';
    }

    public function failed(Throwable $e): void
    {
        Log::error('SyncPendingBookRagDocuments job failed permanently', [
            'error' => $e->getMessage(),
            'exception' => $e::class,
        ]);
    }
}
