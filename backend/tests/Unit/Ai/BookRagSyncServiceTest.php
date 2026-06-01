<?php

use App\Models\Book;
use App\Services\Ai\BookRagDocumentFactory;
use App\Services\Ai\BookRagSyncService;
use App\Services\Ai\Dto\GeminiEmbeddingResult;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\MeilisearchRagDocumentWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

test('book rag sync service embeds text and upserts full document', function (): void {
    config(['ai.rag.embedding_dimensions' => 3]);

    $book = Book::factory()->create(['name' => 'RAG Sync Book']);
    $vector = [0.1, 0.2, 0.3];

    $gemini = Mockery::mock(GeminiClient::class);
    $gemini->shouldReceive('embedText')
        ->once()
        ->andReturn(new GeminiEmbeddingResult(
            vector: $vector,
            dimensions: 3,
            latencyMs: 10,
            model: 'gemini-embedding-2',
        ));

    $writer = Mockery::mock(MeilisearchRagDocumentWriter::class);
    $writer->shouldReceive('upsert')
        ->once()
        ->withArgs(function (array $document) use ($book, $vector): bool {
            return $document['id'] === $book->id
                && $document['name'] === 'RAG Sync Book'
                && ($document['_vectors']['gemini_embedding_2_768'] ?? null) === $vector
                && isset($document['rag_embedding_text']);
        });

    $service = new BookRagSyncService(
        app(BookRagDocumentFactory::class),
        $gemini,
        $writer,
    );

    expect($service->sync($book->id))->toBeTrue();
});

test('book rag sync service returns false when book is missing', function (): void {
    $gemini = Mockery::mock(GeminiClient::class);
    $gemini->shouldNotReceive('embedText');
    $writer = Mockery::mock(MeilisearchRagDocumentWriter::class);
    $writer->shouldNotReceive('upsert');

    $service = new BookRagSyncService(
        app(BookRagDocumentFactory::class),
        $gemini,
        $writer,
    );

    expect($service->sync(999_999))->toBeFalse();
});

test('book rag sync service syncMany uses batch embedding and bulk upsert', function (): void {
    config([
        'ai.rag.embedding_dimensions' => 3,
        'ai.rag.embed_batch_size' => 10,
    ]);

    $books = Book::factory()->count(3)->create();
    $vectors = [
        [0.1, 0.2, 0.3],
        [0.4, 0.5, 0.6],
        [0.7, 0.8, 0.9],
    ];

    $gemini = Mockery::mock(GeminiClient::class);
    $gemini->shouldReceive('embedTexts')
        ->once()
        ->withArgs(function (array $texts): bool {
            return count($texts) === 3;
        })
        ->andReturn(new \App\Services\Ai\Dto\GeminiBatchEmbeddingResult(
            vectors: $vectors,
            dimensions: 3,
            latencyMs: 12,
            model: 'gemini-embedding-2',
        ));
    $gemini->shouldNotReceive('embedText');

    $writer = Mockery::mock(MeilisearchRagDocumentWriter::class);
    $writer->shouldReceive('upsertMany')
        ->once()
        ->withArgs(function (array $documents) use ($books): bool {
            return count($documents) === 3
                && collect($documents)->pluck('id')->sort()->values()->all()
                === $books->pluck('id')->sort()->values()->all();
        });

    $service = new BookRagSyncService(
        app(BookRagDocumentFactory::class),
        $gemini,
        $writer,
    );

    $result = $service->syncMany($books->pluck('id')->all());

    expect($result['success'])->toHaveCount(3)
        ->and($result['failed'])->toBe([])
        ->and($result['rate_limited'])->toBeFalse();
});

test('book rag sync service syncMany stops early on rate limit and keeps unprocessed ids', function (): void {
    config([
        'ai.rag.embedding_dimensions' => 3,
        'ai.rag.embed_batch_size' => 1,
        'ai.rag.rate_limit_stop_on_429' => true,
    ]);

    $books = Book::factory()->count(2)->create();

    $gemini = Mockery::mock(GeminiClient::class);
    $gemini->shouldReceive('embedTexts')
        ->once()
        ->andThrow(new \App\Exceptions\Ai\GeminiClientException(
            'Gemini API returned HTTP 429',
            \App\Exceptions\Ai\GeminiClientException::RATE_LIMIT,
            httpStatus: 429,
        ));

    $writer = Mockery::mock(MeilisearchRagDocumentWriter::class);
    $writer->shouldNotReceive('upsertMany');

    $service = new BookRagSyncService(
        app(BookRagDocumentFactory::class),
        $gemini,
        $writer,
    );

    $result = $service->syncMany($books->pluck('id')->all());

    expect($result['rate_limited'])->toBeTrue()
        ->and($result['unprocessed'])->toHaveCount(2)
        ->and($result['success'])->toBe([]);
});

test('book rag sync service syncMany returns skipped ids for missing books', function (): void {
    $gemini = Mockery::mock(GeminiClient::class);
    $gemini->shouldNotReceive('embedTexts');
    $gemini->shouldNotReceive('embedText');

    $writer = Mockery::mock(MeilisearchRagDocumentWriter::class);
    $writer->shouldNotReceive('upsertMany');

    $service = new BookRagSyncService(
        app(BookRagDocumentFactory::class),
        $gemini,
        $writer,
    );

    $result = $service->syncMany([999_998, 999_999]);

    expect($result['skipped'])->toBe([999_998, 999_999])
        ->and($result['success'])->toBe([])
        ->and($result['failed'])->toBe([]);
});
