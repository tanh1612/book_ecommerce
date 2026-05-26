<?php

namespace App\Services\Search;

use App\Jobs\Search\SyncBookToMeilisearch;
use Illuminate\Support\Facades\DB;

class BookMeilisearchSyncDispatcher
{
    private int $dispatchSuppressionDepth = 0;

    public function dispatch(int $bookId): void
    {
        if ($bookId <= 0 || $this->dispatchSuppressionDepth > 0) {
            return;
        }

        DB::afterCommit(function () use ($bookId): void {
            SyncBookToMeilisearch::dispatch($bookId);
        });
    }

    /**
     * @param  iterable<int>  $bookIds
     */
    public function dispatchMany(iterable $bookIds): void
    {
        foreach ($bookIds as $bookId) {
            $this->dispatch((int) $bookId);
        }
    }

    /**
     * Prevent partial document triggers while an aggregate is being assembled.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function withoutDispatching(callable $callback): mixed
    {
        $this->dispatchSuppressionDepth++;

        try {
            return $callback();
        } finally {
            $this->dispatchSuppressionDepth--;
        }
    }
}
