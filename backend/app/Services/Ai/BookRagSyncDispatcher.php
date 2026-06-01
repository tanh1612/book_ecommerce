<?php

namespace App\Services\Ai;

class BookRagSyncDispatcher
{
    public function __construct(
        private readonly QueueBookRagSyncService $queueBookRagSync,
    ) {}

    public function dispatch(int $bookId): void
    {
        $this->queueBookRagSync->enqueue($bookId);
    }

    /**
     * @param  iterable<int>  $bookIds
     */
    public function dispatchMany(iterable $bookIds): void
    {
        $this->queueBookRagSync->enqueueMany($bookIds);
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function withoutDispatching(callable $callback): mixed
    {
        return $this->queueBookRagSync->withoutDispatching($callback);
    }
}
