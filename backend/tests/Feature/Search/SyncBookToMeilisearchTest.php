<?php

use App\Jobs\Search\SyncBookToMeilisearch;
use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    $engine = bindRecordingScoutEngine();

    $book = Book::factory()->create(['name' => 'Indexed Title']);
    $author = Author::factory()->create(['name' => 'Indexed Author']);
    $category = Category::factory()->create();
    $book->authors()->attach($author);
    $book->categories()->attach($category);
    $book->detail()->update(['description' => 'Indexed description']);

    (new SyncBookToMeilisearch($book->id))->handle();

    $document = $engine->lastDocument();

    expect($document)->not->toBeNull()
        ->and($document['id'])->toBe($book->id)
        ->and($document['name'])->toBe('Indexed Title')
        ->and($document['description'])->toBe('Indexed description')
        ->and($document['author_names'])->toBe(['Indexed Author'])
        ->and($document['author_ids'])->toBe([$author->id])
        ->and($document['category_ids'])->toBe([$category->id]);
});

test('sync book to meilisearch job does not call engine when book was deleted', function (): void {
    $engine = bindRecordingScoutEngine();

    (new SyncBookToMeilisearch(999_999))->handle();

    expect($engine->updates)->toBe([]);
});
