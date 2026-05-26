<?php

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Inventory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('book searchable document contains catalog relations and stock fields', function (): void {
    $book = Book::factory()->create([
        'name' => 'Searchable Book',
        'slug' => 'searchable-book',
        'selling_price' => 90_000,
        'average_rating' => 4.25,
        'review_count' => 8,
    ]);
    $author = Author::factory()->create(['name' => 'Search Author']);
    $category = Category::factory()->create();
    $book->authors()->attach($author);
    $book->categories()->attach($category);
    Inventory::factory()->create([
        'book_id' => $book->id,
        'quantity' => 10,
        'reserved_quantity' => 3,
    ]);

    $document = $book->fresh()->toSearchableArray();

    expect($book->searchableAs())->toBe('books')
        ->and($document)->not->toHaveKey('promotion_id')
        ->and($document['id'])->toBe($book->id)
        ->and($document['name'])->toBe('Searchable Book')
        ->and($document['description'])->toBe($book->detail->description)
        ->and($document['author_names'])->toBe(['Search Author'])
        ->and($document['author_ids'])->toBe([$author->id])
        ->and($document['category_ids'])->toBe([$category->id])
        ->and($document['selling_price'])->toBe(90000.0)
        ->and($document['average_rating'])->toBe(4.25)
        ->and($document['review_count'])->toBe(8)
        ->and($document['available_stock'])->toBe(7)
        ->and($document['in_stock'])->toBeTrue()
        ->and($document['is_active'])->toBeTrue()
        ->and($document['created_at'])->toBeInt();
});

test('book searchable document reflects inactive and out of stock state', function (): void {
    $book = Book::factory()->inactive()->create();
    Inventory::factory()->create([
        'book_id' => $book->id,
        'quantity' => 2,
        'reserved_quantity' => 2,
    ]);

    $document = $book->fresh()->toSearchableArray();

    expect($document)->not->toHaveKey('promotion_id')
        ->and($document['available_stock'])->toBe(0)
        ->and($document['in_stock'])->toBeFalse()
        ->and($document['is_active'])->toBeFalse();
});
