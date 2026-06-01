<?php

namespace App\Services\Ai;

use App\Jobs\Ai\SyncPendingBookRagDocuments;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

class QueueBookRagSyncService
{
    private const CLAIM_BATCH_LUA = <<<'LUA'
local pending_key = KEYS[1]
local processing_key = KEYS[2]
local limit = tonumber(ARGV[1])
local claimed_at = ARGV[2]
local claimed = {}

for _ = 1, limit do
    local id = redis.call('SPOP', pending_key)

    if not id then
        break
    end

    redis.call('HSET', processing_key, id, claimed_at)
    table.insert(claimed, id)
end

return claimed
LUA;

    private int $dispatchSuppressionDepth = 0;

    public function enqueue(int $bookId): void
    {
        if ($bookId <= 0 || $this->dispatchSuppressionDepth > 0) {
            return;
        }

        DB::afterCommit(function () use ($bookId): void {
            try {
                Redis::connection()->sadd($this->pendingKey(), [$bookId]);
                $this->dispatchPendingWorkerIfNeeded();
            } catch (Throwable $e) {
                Log::error('Book RAG sync enqueue failed', [
                    'book_id' => $bookId,
                    'error' => $e->getMessage(),
                    'exception' => $e::class,
                ]);
            }
        });
    }

    /**
     * @param  iterable<int>  $bookIds
     */
    public function enqueueMany(iterable $bookIds): void
    {
        $normalizedIds = [];

        foreach ($bookIds as $bookId) {
            $bookId = (int) $bookId;
            if ($bookId > 0) {
                $normalizedIds[$bookId] = $bookId;
            }
        }

        if ($normalizedIds === [] || $this->dispatchSuppressionDepth > 0) {
            return;
        }

        DB::afterCommit(function () use ($normalizedIds): void {
            try {
                Redis::connection()->sadd($this->pendingKey(), array_values($normalizedIds));
                $this->dispatchPendingWorkerIfNeeded();
            } catch (Throwable $e) {
                Log::error('Book RAG sync bulk enqueue failed', [
                    'book_ids' => array_values($normalizedIds),
                    'error' => $e->getMessage(),
                    'exception' => $e::class,
                ]);
            }
        });
    }

    public function reclaimStaleProcessingClaims(): int
    {
        $ttlSeconds = max(60, (int) config('ai.rag.sync_processing_claim_ttl_seconds', 900));
        $connection = Redis::connection();
        $claims = $connection->hgetall($this->processingClaimsKey());

        if (! is_array($claims) || $claims === []) {
            return 0;
        }

        $now = time();
        $reclaimed = 0;

        try {
            foreach ($claims as $bookId => $claimedAt) {
                if ($now - (int) $claimedAt < $ttlSeconds) {
                    continue;
                }

                $normalizedBookId = (int) $bookId;

                if ($normalizedBookId <= 0) {
                    $connection->hdel($this->processingClaimsKey(), (string) $bookId);

                    continue;
                }

                $connection->sadd($this->pendingKey(), [$normalizedBookId]);
                $connection->hdel($this->processingClaimsKey(), (string) $bookId);
                $reclaimed++;
            }
        } catch (Throwable $e) {
            Log::error('Book RAG sync reclaim stale processing claims failed', [
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }

        if ($reclaimed > 0) {
            Log::warning('Book RAG sync reclaimed stale processing claims', [
                'reclaimed_count' => $reclaimed,
                'ttl_seconds' => $ttlSeconds,
            ]);
        }

        return $reclaimed;
    }

    /**
     * Atomically SPOP pending ids and record processing claims in one Redis script.
     *
     * @return list<int>
     */
    public function claimBatch(int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        $this->reclaimStaleProcessingClaims();

        try {
            $claimed = Redis::connection()->eval(
                self::CLAIM_BATCH_LUA,
                2,
                $this->pendingKey(),
                $this->processingClaimsKey(),
                $limit,
                (string) time(),
            );
        } catch (Throwable $e) {
            Log::error('Book RAG sync atomic claim batch failed', [
                'limit' => $limit,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return [];
        }

        if (! is_array($claimed) || $claimed === []) {
            return [];
        }

        return array_values(array_map(static fn (mixed $id): int => (int) $id, $claimed));
    }

    /**
     * @param  list<int>  $bookIds
     */
    public function requeueMany(array $bookIds): void
    {
        $normalizedIds = [];

        foreach ($bookIds as $bookId) {
            $bookId = (int) $bookId;

            if ($bookId > 0) {
                $normalizedIds[$bookId] = $bookId;
            }
        }

        if ($normalizedIds === []) {
            return;
        }

        try {
            Redis::connection()->sadd($this->pendingKey(), array_values($normalizedIds));
        } catch (Throwable $e) {
            Log::error('Book RAG sync requeue failed', [
                'book_ids' => array_values($normalizedIds),
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }
    }

    /**
     * @param  list<int>  $bookIds
     */
    public function releaseProcessingClaims(array $bookIds): void
    {
        $normalizedIds = array_values(array_unique(array_filter(array_map(
            static fn (mixed $bookId): int => (int) $bookId,
            $bookIds,
        ), static fn (int $bookId): bool => $bookId > 0)));

        if ($normalizedIds === []) {
            return;
        }

        try {
            Redis::connection()->hdel(
                $this->processingClaimsKey(),
                ...array_map(static fn (int $bookId): string => (string) $bookId, $normalizedIds),
            );
        } catch (Throwable $e) {
            Log::error('Book RAG sync release processing claims failed', [
                'book_ids' => $normalizedIds,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }
    }

    /**
     * @param  list<int>  $bookIds
     */
    public function clearRetryCounts(array $bookIds): void
    {
        $normalizedIds = array_values(array_unique(array_filter(array_map(
            static fn (mixed $bookId): int => (int) $bookId,
            $bookIds,
        ), static fn (int $bookId): bool => $bookId > 0)));

        if ($normalizedIds === []) {
            return;
        }

        try {
            Redis::connection()->hdel(
                $this->retryCountsKey(),
                ...array_map(static fn (int $bookId): string => (string) $bookId, $normalizedIds),
            );
        } catch (Throwable $e) {
            Log::error('Book RAG sync clear retry counts failed', [
                'book_ids' => $normalizedIds,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }
    }

    /**
     * @param  list<int>  $bookIds
     * @return array{requeued: list<int>, dead_letter: list<int>}
     */
    public function releaseFailedBookIds(array $bookIds): array
    {
        $normalizedIds = array_values(array_unique(array_filter(array_map(
            static fn (mixed $bookId): int => (int) $bookId,
            $bookIds,
        ), static fn (int $bookId): bool => $bookId > 0)));

        if ($normalizedIds === []) {
            return [
                'requeued' => [],
                'dead_letter' => [],
            ];
        }

        $maxRetries = max(1, (int) config('ai.rag.sync_max_retries', 5));
        $connection = Redis::connection();
        $requeued = [];
        $deadLetter = [];

        try {
            foreach ($normalizedIds as $bookId) {
                $attempts = (int) $connection->hincrby($this->retryCountsKey(), (string) $bookId, 1);

                if ($attempts >= $maxRetries) {
                    $connection->sadd($this->deadLetterKey(), [$bookId]);
                    $connection->srem($this->pendingKey(), [$bookId]);
                    $connection->hdel($this->retryCountsKey(), (string) $bookId);
                    $deadLetter[] = $bookId;

                    Log::warning('Book RAG sync moved book to dead letter after max retries', [
                        'book_id' => $bookId,
                        'attempts' => $attempts,
                        'max_retries' => $maxRetries,
                    ]);

                    continue;
                }

                $connection->sadd($this->pendingKey(), [$bookId]);
                $requeued[] = $bookId;
            }
        } catch (Throwable $e) {
            Log::error('Book RAG sync release failed book ids failed', [
                'book_ids' => $normalizedIds,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            $this->requeueMany($normalizedIds);
            $requeued = $normalizedIds;
        }

        return [
            'requeued' => $requeued,
            'dead_letter' => $deadLetter,
        ];
    }

    /**
     * @param  list<int>  $claimedBookIds
     * @param  array{success: list<int>, failed: list<int>, unprocessed: list<int>, skipped: list<int>, rate_limited: bool}  $result
     * @return array{requeued: list<int>, dead_letter: list<int>, should_dispatch: bool, dispatch_delay_seconds: int}
     */
    public function finalizeBatchResult(array $claimedBookIds, array $result): array
    {
        try {
            $this->clearRetryCounts(array_merge($result['success'], $result['skipped']));

            if ($result['unprocessed'] !== []) {
                $this->requeueMany($result['unprocessed']);
            }

            $failedHandling = $this->releaseFailedBookIds($result['failed']);

            $shouldDispatch = $this->pendingCount() > 0 && ! $result['rate_limited'];
            $dispatchDelaySeconds = 0;

            if ($shouldDispatch && $failedHandling['requeued'] !== []) {
                $dispatchDelaySeconds = max(
                    0,
                    (int) config('ai.rag.sync_failed_retry_delay_seconds', 60),
                );
            }

            return [
                'requeued' => $failedHandling['requeued'],
                'dead_letter' => $failedHandling['dead_letter'],
                'should_dispatch' => $shouldDispatch,
                'dispatch_delay_seconds' => $dispatchDelaySeconds,
            ];
        } finally {
            $this->releaseProcessingClaims($claimedBookIds);
        }
    }

    /**
     * @return list<int>
     */
    public function popBatch(int $limit): array
    {
        return $this->claimBatch($limit);
    }

    public function pendingCount(): int
    {
        return (int) Redis::connection()->scard($this->pendingKey());
    }

    public function deadLetterCount(): int
    {
        return (int) Redis::connection()->scard($this->deadLetterKey());
    }

    public function processingClaimsCount(): int
    {
        return (int) Redis::connection()->hlen($this->processingClaimsKey());
    }

    public function dispatchPendingWorkerIfNeeded(int $delaySeconds = 0): void
    {
        $dispatch = SyncPendingBookRagDocuments::dispatch();

        if ($delaySeconds > 0) {
            $dispatch->delay(now()->addSeconds($delaySeconds));
        }
    }

    /**
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

    private function pendingKey(): string
    {
        return (string) config('ai.rag.sync_pending_key', 'ai:rag:sync:pending_book_ids');
    }

    private function retryCountsKey(): string
    {
        return (string) config('ai.rag.sync_retry_counts_key', 'ai:rag:sync:retry_counts');
    }

    private function deadLetterKey(): string
    {
        return (string) config('ai.rag.sync_dead_letter_key', 'ai:rag:sync:dead_letter_book_ids');
    }

    private function processingClaimsKey(): string
    {
        return (string) config('ai.rag.sync_processing_claims_key', 'ai:rag:sync:processing_claims');
    }
}
