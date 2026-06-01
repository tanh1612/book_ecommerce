<?php

use App\Jobs\Search\SyncBookToMeilisearch;
use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Services\Ai\MeilisearchRagDocumentWriter;
use App\Services\Ai\BookRagSyncDispatcher;
use App\Services\Search\BookMeilisearchSyncDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Scout\EngineManager;
use Tests\Support\RecordingScoutEngine;

uses(RefreshDatabase::class);

function bindRecordingScoutEngine(): RecordingScoutEngine
{
    $engine = new RecordingScoutEngine;
    $manager = app(EngineManager::class);
    $manager->forgetDrivers();
    $manager->extend('recording', fn (): RecordingScoutEngine => $engine);
    config([
        'scout.driver' => 'recording',
        'scout.queue' => false,
    ]);

    return $engine;
}

test('sync book to meilisearch job indexes document with latest relations', function (): void {
    Queue::fake([SyncBookToMeilisearch::class]);

    $engine = bindRecordingScoutEngine();
    $writer = Mockery::mock(MeilisearchRagDocumentWriter::class);
    $writer->shouldReceive('getDocumentVectors')->once()->andReturn(null);
    $writer->shouldNotReceive('upsertVectors');
    app()->instance(MeilisearchRagDocumentWriter::class, $writer);

    $book = app(BookMeilisearchSyncDispatcher::class)->withoutDispatching(function () {
        return app(BookRagSyncDispatcher::class)->withoutDispatching(function () {
            return Book::withoutSyncingToSearch(function () {
                $book = Book::factory()->create(['name' => 'Indexed Title']);
                $author = Author::factory()->create(['name' => 'Indexed Author']);
                $category = Category::factory()->create();
                $book->authors()->attach($author);
                $book->categories()->attach($category);
                $book->detail()->update(['description' => 'Indexed description']);

                return $book->fresh();
            });
        });
    });

    (new SyncBookToMeilisearch($book->id))->handle($writer);

    $document = $engine->lastDocument();

    expect($document)->not->toBeNull()
        ->and($document['id'])->toBe($book->id)
        ->and($document['name'])->toBe('Indexed Title')
        ->and($document['description'])->toBe('Indexed description')
        ->and($document['author_names'])->toBe(['Indexed Author'])
        ->and($document['author_ids'])->toBe($book->authors->pluck('id')->all())
        ->and($document['category_ids'])->toBe($book->categories->pluck('id')->all())
        ->and($document)->toHaveKey('rag_embedding_text')
        ->and($document)->not->toHaveKey('_vectors');
});

test('sync book to meilisearch job preserves existing vectors after catalog sync', function (): void {
    $engine = bindRecordingScoutEngine();
    $vector = array_fill(0, 768, 0.02);
    $book = Book::factory()->create();
    $bookId = $book->id;
    $writer = Mockery::mock(MeilisearchRagDocumentWriter::class);
    $writer->shouldReceive('getDocumentVectors')->once()->with($bookId)->andReturn($vector);
    $writer->shouldReceive('upsertVectors')->once()->with($bookId, $vector);
    app()->instance(MeilisearchRagDocumentWriter::class, $writer);

    (new SyncBookToMeilisearch($bookId))->handle($writer);

    expect($engine->lastDocument())->not->toHaveKey('_vectors');
});

test('sync book to meilisearch job does not call engine when book was deleted', function (): void {
    $engine = bindRecordingScoutEngine();
    $writer = Mockery::mock(MeilisearchRagDocumentWriter::class);
    $writer->shouldNotReceive('getDocumentVectors');
    app()->instance(MeilisearchRagDocumentWriter::class, $writer);

    (new SyncBookToMeilisearch(999_999))->handle($writer);

    expect($engine->updates)->toBe([]);
});
