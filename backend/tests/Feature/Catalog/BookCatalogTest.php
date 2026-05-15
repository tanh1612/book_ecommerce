<?php

use App\Models\Book;
use App\Models\Category;
use App\Models\Publisher;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('books index defaults to 40 items per page', function (): void {
    Book::factory()->count(45)->create();

    $response = $this->getJson('/api/v1/books');

    $response->assertOk();
    expect($response->json('meta.per_page'))->toBe(40);
    expect(count($response->json('data')))->toBe(40);
    expect($response->json('meta.total'))->toBe(45);
});

test('books index excludes inactive books', function (): void {
    Book::factory()->count(2)->create();
    Book::factory()->inactive()->create(['slug' => 'hidden-book']);

    $response = $this->getJson('/api/v1/books');

    $response->assertOk();
    $slugs = collect($response->json('data'))->pluck('slug')->all();
    expect($slugs)->not->toContain('hidden-book');
});

test('book show returns 404 for inactive slug', function (): void {
    Book::factory()->inactive()->create(['slug' => 'inactive-book']);

    $this->getJson('/api/v1/books/inactive-book')->assertNotFound();
});

test('book show returns detail for active slug', function (): void {
    Book::factory()->create([
        'slug' => 'active-book',
        'name' => 'Active Title',
    ]);

    $this->getJson('/api/v1/books/active-book')
        ->assertOk()
        ->assertJsonPath('data.slug', 'active-book')
        ->assertJsonPath('data.name', 'Active Title')
        ->assertJsonPath('data.detail.language', 'vi');
});

test('books index filters by parent category including descendants', function (): void {
    $parent = Category::factory()->create(['slug' => 'parent-cat']);
    $child = Category::factory()->child($parent)->create(['slug' => 'child-cat']);
    $other = Category::factory()->create(['slug' => 'other-cat']);

    $match = Book::factory()->create(['slug' => 'match-book']);
    $match->categories()->attach($child);

    $noMatch = Book::factory()->create(['slug' => 'other-book']);
    $noMatch->categories()->attach($other);

    $response = $this->getJson('/api/v1/books?category=parent-cat');

    $response->assertOk();
    $slugs = collect($response->json('data'))->pluck('slug')->all();
    expect($slugs)->toContain('match-book')->not->toContain('other-book');
});

test('books index filters by price range on selling_price', function (): void {
    Book::factory()->create(['slug' => 'low-book', 'selling_price' => 50_000]);
    Book::factory()->create(['slug' => 'high-book', 'selling_price' => 500_000]);

    $response = $this->getJson('/api/v1/books?price_min=40000&price_max=60000');

    $response->assertOk();
    $slugs = collect($response->json('data'))->pluck('slug')->all();
    expect($slugs)->toContain('low-book')->not->toContain('high-book');
});

test('books index filters by publisher id', function (): void {
    $publisherA = Publisher::factory()->create();
    $publisherB = Publisher::factory()->create();

    Book::factory()->create(['slug' => 'pub-a', 'publisher_id' => $publisherA->id]);
    Book::factory()->create(['slug' => 'pub-b', 'publisher_id' => $publisherB->id]);

    $response = $this->getJson('/api/v1/books?publisher='.$publisherA->id);

    $response->assertOk();
    $slugs = collect($response->json('data'))->pluck('slug')->all();
    expect($slugs)->toContain('pub-a')->not->toContain('pub-b');
});

test('books index filters by supplier slug', function (): void {
    $supplierA = Supplier::factory()->create(['slug' => 'supplier-a']);
    $supplierB = Supplier::factory()->create(['slug' => 'supplier-b']);

    Book::factory()->create(['slug' => 'sup-a', 'supplier_id' => $supplierA->id]);
    Book::factory()->create(['slug' => 'sup-b', 'supplier_id' => $supplierB->id]);

    $response = $this->getJson('/api/v1/books?supplier=supplier-a');

    $response->assertOk();
    $slugs = collect($response->json('data'))->pluck('slug')->all();
    expect($slugs)->toContain('sup-a')->not->toContain('sup-b');
});

test('books index rejects per_page above 40', function (): void {
    $this->getJson('/api/v1/books?per_page=41')->assertStatus(422);
});

test('books index rejects price_min greater than price_max', function (): void {
    $this->getJson('/api/v1/books?price_min=100&price_max=50')->assertStatus(422);
});

test('books filters endpoint returns metadata', function (): void {
    $supplier = Supplier::factory()->create();
    $publisher = Publisher::factory()->create();
    Book::factory()->create([
        'supplier_id' => $supplier->id,
        'publisher_id' => $publisher->id,
    ]);

    $response = $this->getJson('/api/v1/books/filters');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'categories',
                'publishers',
                'suppliers',
                'suggested_price_ranges',
            ],
        ]);

    expect($response->json('data.publishers'))->not->toBeEmpty();
    expect($response->json('data.suppliers'))->not->toBeEmpty();
});

test('books filters route is not captured by slug route', function (): void {
    $this->getJson('/api/v1/books/filters')->assertOk();
});
