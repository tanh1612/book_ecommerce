<?php

use App\Models\Author;
use App\Models\Book;
use App\Models\BookImage;
use App\Models\Inventory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['scout.driver' => 'collection']);
});

test('book suggestions returns books matching keyword in name', function (): void {
    Book::factory()->create(['name' => 'Harry Meili Unique', 'slug' => 'harry-meili-unique']);
    Book::factory()->create(['name' => 'Unrelated Volume', 'slug' => 'unrelated-volume']);

    $response = $this->getJson('/api/v1/books/suggestions?keyword=harry+meili');

    $response->assertOk();
    $slugs = collect($response->json('data'))->pluck('slug')->all();
    expect($slugs)->toContain('harry-meili-unique')->not->toContain('unrelated-volume');
});

test('book suggestions finds book by indexed author name', function (): void {
    $book = Book::factory()->create(['name' => 'Carrier For Author Suggest', 'slug' => 'carrier-author-suggest']);
    $author = Author::factory()->create(['name' => 'Suggest Author Scout']);
    $book->authors()->attach($author);

    $response = $this->getJson('/api/v1/books/suggestions?keyword=suggest+author+scout');

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('slug')->all())->toContain('carrier-author-suggest');
});

test('book suggestions thumbnail_url uses lowest sort_order image over column thumbnail', function (): void {
    $book = Book::factory()->create([
        'name' => 'Image Sort Suggest Book',
        'slug' => 'image-sort-suggest-book',
        'thumbnail' => 'https://cdn.example.com/column-fallback.jpg',
    ]);

    BookImage::factory()->create([
        'book_id' => $book->id,
        'image_url' => 'https://cdn.example.com/secondary.jpg',
        'sort_order' => 2,
    ]);
    BookImage::factory()->create([
        'book_id' => $book->id,
        'image_url' => 'https://cdn.example.com/primary.jpg',
        'sort_order' => 1,
    ]);

    $response = $this->getJson('/api/v1/books/suggestions?keyword=image+sort+suggest');

    $response->assertOk();
    $item = collect($response->json('data'))->firstWhere('slug', 'image-sort-suggest-book');

    expect($item)->not->toBeNull()
        ->and($item['thumbnail_url'])->toBe('https://cdn.example.com/primary.jpg');
});

test('book suggestions includes inactive and out of stock books', function (): void {
    Book::factory()->inactive()->create([
        'name' => 'Inactive Suggest Unique',
        'slug' => 'inactive-suggest-unique',
    ]);

    $outOfStock = Book::factory()->create([
        'name' => 'Out Of Stock Suggest Unique',
        'slug' => 'out-of-stock-suggest-unique',
    ]);
    Inventory::factory()->create([
        'book_id' => $outOfStock->id,
        'quantity' => 0,
        'reserved_quantity' => 0,
    ]);

    $response = $this->getJson('/api/v1/books/suggestions?keyword=suggest+unique');

    $response->assertOk();
    $slugs = collect($response->json('data'))->pluck('slug')->all();

    expect($slugs)->toContain('inactive-suggest-unique', 'out-of-stock-suggest-unique');
});

test('book suggestions payload only contains minimal fields', function (): void {
    Book::factory()->create([
        'name' => 'Minimal Payload Book',
        'slug' => 'minimal-payload-book',
        'thumbnail' => 'https://cdn.example.com/thumb.jpg',
    ]);

    $response = $this->getJson('/api/v1/books/suggestions?keyword=minimal+payload');

    $response->assertOk();
    $item = collect($response->json('data'))->firstWhere('slug', 'minimal-payload-book');

    expect($item)->not->toBeNull()
        ->and(array_keys($item))->toEqual(['id', 'name', 'slug', 'thumbnail_url'])
        ->and($item['thumbnail_url'])->toBe('https://cdn.example.com/thumb.jpg');
});

test('book suggestions respects limit and rejects limit above ten', function (): void {
    foreach (range(1, 5) as $index) {
        Book::factory()->create([
            'name' => "Limit Suggest Book {$index}",
            'slug' => "limit-suggest-book-{$index}",
        ]);
    }

    $limited = $this->getJson('/api/v1/books/suggestions?keyword=limit+suggest&limit=2');
    $limited->assertOk();
    expect($limited->json('data'))->toHaveCount(2);

    $this->getJson('/api/v1/books/suggestions?keyword=limit+suggest&limit=11')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['limit']);
});

test('book suggestions default limit is eight', function (): void {
    foreach (range(1, 10) as $index) {
        Book::factory()->create([
            'name' => "Default Limit Suggest {$index}",
            'slug' => "default-limit-suggest-{$index}",
        ]);
    }

    $response = $this->getJson('/api/v1/books/suggestions?keyword=default+limit+suggest');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(8);
});

test('book suggestions rejects empty or single character keyword', function (): void {
    $this->getJson('/api/v1/books/suggestions?keyword=')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['keyword']);

    $this->getJson('/api/v1/books/suggestions?keyword=a')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['keyword']);
});

test('book suggestions route is not resolved as book slug', function (): void {
    Book::factory()->create(['name' => 'Route Guard Book', 'slug' => 'suggestions']);

    $response = $this->getJson('/api/v1/books/suggestions?keyword=route+guard');

    $response->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name', 'slug', 'thumbnail_url']]])
        ->assertJsonMissing(['effective_price', 'promotion', 'available_stock']);

    $hit = collect($response->json('data'))->firstWhere('slug', 'suggestions');
    expect($hit)->not->toBeNull()
        ->and($hit['name'])->toBe('Route Guard Book');
});

test('book suggestions trims keyword whitespace', function (): void {
    Book::factory()->create(['name' => 'Trimmed Suggest Hit', 'slug' => 'trimmed-suggest-hit']);

    $response = $this->getJson('/api/v1/books/suggestions?keyword=%20%20trimmed+suggest%20%20');

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('slug')->all())->toContain('trimmed-suggest-hit');
});
