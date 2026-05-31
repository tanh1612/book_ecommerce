<?php

use App\Jobs\Ai\SyncPendingBookRagDocuments;
use App\Services\Ai\QueueBookRagSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'ai.rag.sync_pending_key' => 'ai:rag:sync:pending_book_ids:test',
        'ai.rag.sync_retry_counts_key' => 'ai:rag:sync:retry_counts:test',
        'ai.rag.sync_dead_letter_key' => 'ai:rag:sync:dead_letter_book_ids:test',
        'ai.rag.sync_processing_claims_key' => 'ai:rag:sync:processing_claims:test',
        'ai.rag.sync_processing_claim_ttl_seconds' => 60,
        'ai.rag.sync_max_retries' => 3,
        'ai.rag.sync_failed_retry_delay_seconds' => 30,
        'ai.rag.sync_queue' => 'ai-rag-sync',
    ]);

    Redis::connection()->del([
        config('ai.rag.sync_pending_key'),
        config('ai.rag.sync_retry_counts_key'),
        config('ai.rag.sync_dead_letter_key'),
        config('ai.rag.sync_processing_claims_key'),
    ]);
    Queue::fake();
});

test('queue book rag sync service deduplicates pending ids and dispatches worker once', function (): void {
    $service = app(QueueBookRagSyncService::class);

    $service->enqueue(10);
    $service->enqueue(10);
    $service->enqueue(20);

    expect(Redis::connection()->scard(config('ai.rag.sync_pending_key')))->toBe(2);

    Queue::assertPushed(SyncPendingBookRagDocuments::class, 1);
});

test('queue book rag sync service claim batch atomically removes ids from pending and records processing claim', function (): void {
    Redis::connection()->sadd(config('ai.rag.sync_pending_key'), [1, 2, 3, 4, 5]);

    $service = app(QueueBookRagSyncService::class);
    $claimed = $service->claimBatch(2);

    expect($claimed)->toHaveCount(2)
        ->and($service->pendingCount())->toBe(3)
        ->and($service->processingClaimsCount())->toBe(2);
});

test('queue book rag sync service release failed book ids requeues until max retries', function (): void {
    $service = app(QueueBookRagSyncService::class);

    $first = $service->releaseFailedBookIds([42]);
    $second = $service->releaseFailedBookIds([42]);
    $third = $service->releaseFailedBookIds([42]);

    expect($first['requeued'])->toBe([42])
        ->and($first['dead_letter'])->toBe([])
        ->and($second['requeued'])->toBe([42])
        ->and($third['dead_letter'])->toBe([42])
        ->and($service->pendingCount())->toBe(0)
        ->and($service->deadLetterCount())->toBe(1);
});

test('queue book rag sync service finalize batch result clears skipped ids without requeue', function (): void {
    Redis::connection()->sadd(config('ai.rag.sync_pending_key'), [99]);
    Redis::connection()->hset(config('ai.rag.sync_retry_counts_key'), '99', 2);
    Redis::connection()->hset(config('ai.rag.sync_processing_claims_key'), '99', (string) time());

    $service = app(QueueBookRagSyncService::class);

    $finalized = $service->finalizeBatchResult([99], [
        'success' => [],
        'failed' => [],
        'unprocessed' => [],
        'skipped' => [99],
        'rate_limited' => false,
    ]);

    expect($finalized['should_dispatch'])->toBeTrue()
        ->and(Redis::connection()->hget(config('ai.rag.sync_retry_counts_key'), '99'))->toBeNull()
        ->and($service->processingClaimsCount())->toBe(0);
});

test('queue book rag sync service finalize batch result does not dispatch on rate limit', function (): void {
    Redis::connection()->sadd(config('ai.rag.sync_pending_key'), [10]);

    $service = app(QueueBookRagSyncService::class);

    $finalized = $service->finalizeBatchResult([10], [
        'success' => [],
        'failed' => [],
        'unprocessed' => [10],
        'skipped' => [],
        'rate_limited' => true,
    ]);

    expect($finalized['should_dispatch'])->toBeFalse()
        ->and($service->pendingCount())->toBe(1)
        ->and($service->processingClaimsCount())->toBe(0);
});

test('queue book rag sync service finalize batch result delays dispatch after failed requeue', function (): void {
    $service = app(QueueBookRagSyncService::class);

    $finalized = $service->finalizeBatchResult([55], [
        'success' => [],
        'failed' => [55],
        'unprocessed' => [],
        'skipped' => [],
        'rate_limited' => false,
    ]);

    expect($finalized['should_dispatch'])->toBeTrue()
        ->and($finalized['dispatch_delay_seconds'])->toBe(30)
        ->and($service->pendingCount())->toBe(1)
        ->and($service->processingClaimsCount())->toBe(0);
});

test('queue book rag sync service reclaims stale processing claims back to pending', function (): void {
    Redis::connection()->hset(
        config('ai.rag.sync_processing_claims_key'),
        '77',
        (string) (time() - 120),
    );

    $service = app(QueueBookRagSyncService::class);

    expect($service->reclaimStaleProcessingClaims())->toBe(1)
        ->and($service->pendingCount())->toBe(1)
        ->and($service->processingClaimsCount())->toBe(0);
});
