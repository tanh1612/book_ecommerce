<?php

namespace App\Jobs\Search;

use App\Models\Book;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncBookToMeilisearch implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public int $uniqueFor = 3600;

    public function __construct(
        public int $bookId,
    ) {}

    public function handle(): void
    {
        $book = Book::query()->find($this->bookId);

        if ($book === null) {
            return;
        }

        try {
            $book->searchableSync();
        } catch (Throwable $e) {
            Log::error('SyncBookToMeilisearch failed', [
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
        Log::error('SyncBookToMeilisearch job failed permanently', [
            'book_id' => $this->bookId,
            'error' => $e->getMessage(),
            'exception' => $e::class,
        ]);
    }
}
