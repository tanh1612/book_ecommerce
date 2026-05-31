<?php

use App\Jobs\Ai\SyncPendingBookRagDocuments;
use App\Jobs\Search\SyncBookToMeilisearch;
use App\Models\Book;
use App\Services\Ai\BookRagSyncDispatcher;
use App\Services\Ai\BookRagSyncService;
use App\Services\Ai\QueueBookRagSyncService;
use App\Services\Search\BookMeilisearchSyncDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'ai.gemini.api_key' => 'test-api-key',
        'ai.rag.embedding_dimensions' => 3,
        'ai.rag.embed_batch_size' => 25,
        'ai.rag.sync_pending_key' => 'ai:rag:sync:pending_book_ids:test',
        'ai.rag.sync_retry_counts_key' => 'ai:rag:sync:retry_counts:test',
        'ai.rag.sync_dead_letter_key' => 'ai:rag:sync:dead_letter_book_ids:test',
        'ai.rag.sync_processing_claims_key' => 'ai:rag:sync:processing_claims:test',
        'ai.rag.sync_batch_size' => 2,
        'ai.rag.sync_batch_sleep_ms' => 0,
        'ai.rag.sync_failed_retry_delay_seconds' => 0,
    ]);

    Redis::connection()->del([
        config('ai.rag.sync_pending_key'),
        config('ai.rag.sync_retry_counts_key'),
        config('ai.rag.sync_dead_letter_key'),
        config('ai.rag.sync_processing_claims_key'),
    ]);
    Http::preventStrayRequests();
});

test('ai sync book rag documents command syncs single book with fake gemini', function (): void {
    Queue::fake([SyncBookToMeilisearch::class]);

    Http::fake([
        '*:embedContent*' => Http::response([
            'embedding' => ['values' => [0.1, 0.2, 0.3]],
        ], 200),
    ]);

    $writer = Mockery::mock(\App\Services\Ai\MeilisearchRagDocumentWriter::class);
    $writer->shouldReceive('upsert')->once();
    app()->instance(\App\Services\Ai\MeilisearchRagDocumentWriter::class, $writer);

    $book = app(BookMeilisearchSyncDispatcher::class)->withoutDispatching(function () {
        return app(BookRagSyncDispatcher::class)->withoutDispatching(function () {
            return Book::withoutSyncingToSearch(fn () => Book::factory()->create(['name' => 'Command Sync Book']));
        });
    });

    $exitCode = Artisan::call('ai:sync-book-rag-documents', ['--book-id' => $book->id]);

    expect($exitCode)->toBe(0);
});

test('book detail update enqueues pending rag worker instead of per book gemini jobs', function (): void {
    Queue::fake([SyncPendingBookRagDocuments::class]);

    $book = Book::factory()->create();
    $book->detail()->update(['description' => 'Updated for RAG sync']);

    Queue::assertPushed(SyncPendingBookRagDocuments::class, 1);
    Queue::assertNotPushed(\App\Jobs\Ai\SyncBookRagDocument::class);
});

test('sync pending book rag documents processes at most one batch per job', function (): void {
    Queue::fake([SyncPendingBookRagDocuments::class]);

    Redis::connection()->sadd(config('ai.rag.sync_pending_key'), [1, 2, 3, 4]);

    $syncService = Mockery::mock(BookRagSyncService::class);
    $syncService->shouldReceive('syncMany')
        ->once()
        ->withArgs(function (array $bookIds): bool {
            return count($bookIds) === 2;
        })
        ->andReturn([
            'success' => [1, 2],
            'failed' => [],
            'unprocessed' => [],
            'skipped' => [],
            'rate_limited' => false,
        ]);
    app()->instance(BookRagSyncService::class, $syncService);

    $queueService = app(QueueBookRagSyncService::class);
    app(SyncPendingBookRagDocuments::class)->handle($queueService, $syncService);

    expect($queueService->pendingCount())->toBe(2);
});

test('sync pending book rag documents removes skipped missing book ids from pending', function (): void {
    Queue::fake([SyncPendingBookRagDocuments::class]);

    Redis::connection()->sadd(config('ai.rag.sync_pending_key'), [999_999, 3, 4]);

    $syncService = Mockery::mock(BookRagSyncService::class);
    $syncService->shouldReceive('syncMany')
        ->once()
        ->andReturn([
            'success' => [],
            'failed' => [],
            'unprocessed' => [],
            'skipped' => [999_999],
            'rate_limited' => false,
        ]);
    app()->instance(BookRagSyncService::class, $syncService);

    $queueService = app(QueueBookRagSyncService::class);
    app(SyncPendingBookRagDocuments::class)->handle($queueService, $syncService);

    expect($queueService->pendingCount())->toBe(1);
});

test('sync pending book rag documents dispatches delayed worker after unexpected batch failure', function (): void {
    Queue::fake([SyncPendingBookRagDocuments::class]);

    Redis::connection()->sadd(config('ai.rag.sync_pending_key'), [1, 2]);

    config(['ai.rag.sync_failed_retry_delay_seconds' => 45]);

    $syncService = Mockery::mock(BookRagSyncService::class);
    $syncService->shouldReceive('syncMany')
        ->once()
        ->andThrow(new RuntimeException('Unexpected sync failure'));
    app()->instance(BookRagSyncService::class, $syncService);

    $queueService = app(QueueBookRagSyncService::class);
    app(SyncPendingBookRagDocuments::class)->handle($queueService, $syncService);

    expect($queueService->pendingCount())->toBe(2)
        ->and($queueService->processingClaimsCount())->toBe(0);

    Queue::assertPushed(SyncPendingBookRagDocuments::class, function (SyncPendingBookRagDocuments $job): bool {
        return $job->delay !== null;
    });
});

test('missing vectors limit applies after filtering missing books', function (): void {
    Queue::fake([SyncBookToMeilisearch::class]);

    $books = app(BookMeilisearchSyncDispatcher::class)->withoutDispatching(function () {
        return app(BookRagSyncDispatcher::class)->withoutDispatching(function () {
            return Book::withoutSyncingToSearch(fn () => Book::factory()->count(3)->create());
        });
    });

    $bookWithVectorId = $books->first()->id;

    $writer = Mockery::mock(\App\Services\Ai\MeilisearchRagDocumentWriter::class);
    $writer->shouldReceive('getDocumentVectors')
        ->andReturnUsing(function (int $bookId) use ($bookWithVectorId): ?array {
            return $bookId === $bookWithVectorId ? [0.1, 0.2, 0.3] : null;
        });
    $writer->shouldReceive('upsertMany')->once();
    app()->instance(\App\Services\Ai\MeilisearchRagDocumentWriter::class, $writer);

    Http::fake([
        '*:batchEmbedContents*' => Http::response([
            'embeddings' => [
                ['values' => [0.1, 0.2, 0.3]],
            ],
        ], 200),
    ]);

    config([
        'ai.rag.sync_batch_size' => 5,
        'ai.rag.embed_batch_size' => 5,
    ]);

    $exitCode = Artisan::call('ai:sync-book-rag-documents', [
        '--all' => true,
        '--missing-vectors' => true,
        '--limit' => 1,
    ]);

    expect($exitCode)->toBe(0);

    Http::assertSent(function ($request): bool {
        if (! str_contains($request->url(), ':batchEmbedContents')) {
            return false;
        }

        return count($request->data()['requests'] ?? []) === 1;
    });
});

test('full rag sync uses batch embedding instead of per book requests', function (): void {
    Queue::fake([SyncBookToMeilisearch::class]);

    Http::fake([
        '*:batchEmbedContents*' => Http::response([
            'embeddings' => [
                ['values' => [0.1, 0.2, 0.3]],
                ['values' => [0.4, 0.5, 0.6]],
            ],
        ], 200),
    ]);

    config([
        'ai.rag.sync_batch_size' => 2,
        'ai.rag.embed_batch_size' => 2,
    ]);

    $writer = Mockery::mock(\App\Services\Ai\MeilisearchRagDocumentWriter::class);
    $writer->shouldReceive('upsertMany')->once();
    app()->instance(\App\Services\Ai\MeilisearchRagDocumentWriter::class, $writer);

    $books = app(BookMeilisearchSyncDispatcher::class)->withoutDispatching(function () {
        return app(BookRagSyncDispatcher::class)->withoutDispatching(function () {
            return Book::withoutSyncingToSearch(fn () => Book::factory()->count(2)->create());
        });
    });

    $exitCode = Artisan::call('ai:sync-book-rag-documents', [
        '--all' => true,
        '--limit' => 2,
    ]);

    expect($exitCode)->toBe(0);

    Http::assertSentCount(1);
    Http::assertSent(fn ($request): bool => str_contains($request->url(), ':batchEmbedContents'));
});
