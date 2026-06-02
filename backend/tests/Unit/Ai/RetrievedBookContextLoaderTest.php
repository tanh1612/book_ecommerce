<?php

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Inventory;
use App\Services\Ai\Dto\BookRagRetrievedDocument;
use App\Services\Ai\RetrievedBookContextLoader;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

test('load returns current mysql price rating and stock for retrieved book', function (): void {
    $book = Book::factory()->create([
        'name' => 'Dac Nhan Tam',
        'slug' => 'dac-nhan-tam',
        'selling_price' => 86000,
        'average_rating' => 4.5,
        'review_count' => 120,
    ]);

    $author = Author::factory()->create(['name' => 'Dale Carnegie']);
    $category = Category::factory()->create(['name' => 'Ky nang song']);
    $book->authors()->attach($author);
    $book->categories()->attach($category);

    Inventory::factory()->create([
        'book_id' => $book->id,
        'quantity' => 10,
        'reserved_quantity' => 2,
    ]);

    $documents = [
        new BookRagRetrievedDocument((int) $book->id, 0.82, 'Dac Nhan Tam', 'dac-nhan-tam', []),
    ];

    $contexts = app(RetrievedBookContextLoader::class)->load($documents);

    expect($contexts)->toHaveCount(1)
        ->and($contexts[0]->bookId)->toBe($book->id)
        ->and($contexts[0]->sellingPrice)->toEqual(86000.0)
        ->and($contexts[0]->averageRating)->toEqual(4.5)
        ->and($contexts[0]->reviewCount)->toBe(120)
        ->and($contexts[0]->availableStock)->toBe(8)
        ->and($contexts[0]->inStock)->toBeTrue()
        ->and($contexts[0]->authorNames)->toBe(['Dale Carnegie'])
        ->and($contexts[0]->categoryNames)->toBe(['Ky nang song'])
        ->and($contexts[0]->similarityScore)->toBe(0.82);
});

test('load preserves retrieval order across multiple books', function (): void {
    $firstBook = Book::factory()->create(['name' => 'Sach A', 'slug' => 'sach-a']);
    $secondBook = Book::factory()->create(['name' => 'Sach B', 'slug' => 'sach-b']);

    $documents = [
        new BookRagRetrievedDocument((int) $secondBook->id, 0.70, 'Sach B', 'sach-b', []),
        new BookRagRetrievedDocument((int) $firstBook->id, 0.82, 'Sach A', 'sach-a', []),
    ];

    $contexts = app(RetrievedBookContextLoader::class)->load($documents);

    expect($contexts)->toHaveCount(2)
        ->and($contexts[0]->bookId)->toBe($secondBook->id)
        ->and($contexts[1]->bookId)->toBe($firstBook->id);
});

test('load includes inactive books when they are explicit retrieval targets', function (): void {
    $inactiveBook = Book::factory()->inactive()->create([
        'name' => 'Sach inactive',
        'slug' => 'sach-inactive',
    ]);

    $documents = [
        new BookRagRetrievedDocument((int) $inactiveBook->id, 0.90, 'Sach inactive', 'sach-inactive', []),
    ];

    $contexts = app(RetrievedBookContextLoader::class)->load($documents);

    expect($contexts)->toHaveCount(1)
        ->and($contexts[0]->bookId)->toBe($inactiveBook->id)
        ->and($contexts[0]->inStock)->toBeFalse();
});

test('load truncates long descriptions with ascii ellipsis', function (): void {
    config(['ai.rag.prompt_context_max_description_chars' => 20]);

    $book = Book::factory()->create();
    $book->detail()->update([
        'description' => str_repeat('a', 40),
    ]);
    $book->load('detail');

    $documents = [
        new BookRagRetrievedDocument((int) $book->id, 0.80, (string) $book->name, (string) $book->slug, []),
    ];

    $contexts = app(RetrievedBookContextLoader::class)->load($documents);

    expect($contexts[0]->descriptionShort)->toBe(str_repeat('a', 20).'...')
        ->not->toContain('â');
});
