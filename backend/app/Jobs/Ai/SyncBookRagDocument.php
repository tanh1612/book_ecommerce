<?php

namespace App\Jobs\Ai;

use App\Services\Ai\BookRagSyncService;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncBookRagDocument implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public int $uniqueFor = 3600;

    public function __construct(
        public int $bookId,
    ) {
        $this->onQueue((string) config('ai.rag.sync_queue', 'ai-rag-sync'));
    }

    public function handle(BookRagSyncService $bookRagSyncService): void
    {
        try {
            $bookRagSyncService->sync($this->bookId);
        } catch (Throwable $e) {
            Log::error('SyncBookRagDocument failed', [
                'book_id' => $this->bookId,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            throw $e;
        }
    }

    public function uniqueId(): string
    {
        return (string) $this->bookId;
    }

    public function failed(Throwable $e): void
    {
        Log::error('SyncBookRagDocument job failed permanently', [
            'book_id' => $this->bookId,
            'error' => $e->getMessage(),
            'exception' => $e::class,
        ]);
    }
}
