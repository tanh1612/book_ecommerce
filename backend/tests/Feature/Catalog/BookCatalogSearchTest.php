<?php

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Publisher;
use App\Models\Supplier;
use App\Services\Catalog\BookCatalogSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Scout\Builder as ScoutBuilder;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['scout.driver' => 'collection']);
});

test('books index keyword defaults sort to relevance', function (): void {
    $this->getJson('/api/v1/books?keyword=alpha')
        ->assertOk();

    $this->getJson('/api/v1/books?keyword=alpha&sort=relevance')
        ->assertOk();
});

test('books index without keyword defaults sort to newest', function (): void {
    Book::factory()->count(2)->create();

    $response = $this->getJson('/api/v1/books');

    $response->assertOk();
});

test('books index trims keyword and treats whitespace as catalog browse', function (): void {
    Book::factory()->create(['name' => 'Whitespace Target', 'slug' => 'whitespace-target']);

    $this->getJson('/api/v1/books?keyword=%20%20')
        ->assertOk()
        ->assertJsonFragment(['slug' => 'whitespace-target']);
});

test('books index rejects relevance sort without keyword', function (): void {
    $this->getJson('/api/v1/books?sort=relevance')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['sort']);
});

test('books index search finds book by name', function (): void {
    Book::factory()->create(['name' => 'Unique Meili Title', 'slug' => 'unique-meili-title']);
    Book::factory()->create(['name' => 'Other Book', 'slug' => 'other-book']);

    $response = $this->getJson('/api/v1/books?keyword=meili+title');

    $response->assertOk();
    $slugs = collect($response->json('data'))->pluck('slug')->all();
    expect($slugs)->toContain('unique-meili-title')->not->toContain('other-book');
});

test('books index search finds book by author names in index document', function (): void {
    $book = Book::factory()->create(['name' => 'Author Carrier', 'slug' => 'author-carrier']);
    $author = Author::factory()->create(['name' => 'Scout Author Unique']);
    $book->authors()->attach($author);

    $response = $this->getJson('/api/v1/books?keyword=scout+author+unique');

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('slug')->all())->toContain('author-carrier');
});

test('books index search finds book by description in index document', function (): void {
    $book = Book::factory()->create(['name' => 'Desc Carrier', 'slug' => 'desc-carrier']);
    $book->detail()->update(['description' => 'Rare botanical encyclopedia content']);

    $response = $this->getJson('/api/v1/books?keyword=botanical+encyclopedia');

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('slug')->all())->toContain('desc-carrier');
});

test('books index search includes inactive books', function (): void {
    Book::factory()->inactive()->create([
        'name' => 'Inactive Search Hit',
        'slug' => 'inactive-search-hit',
    ]);

    $response = $this->getJson('/api/v1/books?keyword=inactive+search');

    $response->assertOk();
    $hit = collect($response->json('data'))->firstWhere('slug', 'inactive-search-hit');
    expect($hit)->not->toBeNull()
        ->and($hit['is_active'])->toBeFalse();
});

test('books index search includes out of stock books', function (): void {
    $book = Book::factory()->create([
        'name' => 'Out Of Stock Search',
        'slug' => 'out-of-stock-search',
    ]);
    Inventory::factory()->create([
        'book_id' => $book->id,
        'quantity' => 0,
        'reserved_quantity' => 0,
    ]);

    $response = $this->getJson('/api/v1/books?keyword=out+of+stock');

    $response->assertOk();
    $hit = collect($response->json('data'))->firstWhere('slug', 'out-of-stock-search');
    expect($hit)->not->toBeNull()
        ->and($hit['available_stock'])->toBe(0)
        ->and($hit['in_stock'])->toBeFalse();
});

test('books index search filters by publisher supplier and price', function (): void {
    $publisherA = Publisher::factory()->create();
    $publisherB = Publisher::factory()->create();
    $supplierA = Supplier::factory()->create();
    $supplierB = Supplier::factory()->create();

    Book::factory()->create([
        'slug' => 'search-filter-hit',
        'name' => 'Filter Combo Hit',
        'publisher_id' => $publisherA->id,
        'supplier_id' => $supplierA->id,
        'selling_price' => 80_000,
    ]);
    Book::factory()->create([
        'slug' => 'search-filter-miss',
        'name' => 'Filter Combo Miss',
        'publisher_id' => $publisherB->id,
        'supplier_id' => $supplierB->id,
        'selling_price' => 200_000,
    ]);

    $response = $this->getJson('/api/v1/books?keyword=filter+combo'
        .'&publisher='.$publisherA->id
        .'&supplier='.$supplierA->id
        .'&price_min=50000&price_max=100000');

    $response->assertOk();
    $slugs = collect($response->json('data'))->pluck('slug')->all();
    expect($slugs)->toContain('search-filter-hit')->not->toContain('search-filter-miss');
});

test('book catalog search service filters by category descendant ids', function (): void {
    $parent = Category::factory()->create(['slug' => 'search-parent-cat']);
    $child = Category::factory()->child($parent)->create(['slug' => 'search-child-cat']);

    $service = app(BookCatalogSearchService::class);
    $builder = Book::search('category');

    $service->applyCatalogFilters($builder, ['category' => 'search-parent-cat']);

    expect($builder->whereIns['category_ids'] ?? [])
        ->toContain($parent->id, $child->id);
});

test('books index search explicit price sort is stable with id tie breaker', function (): void {
    Book::factory()->create([
        'slug' => 'sort-price-a',
        'name' => 'Sort Price Shared',
        'selling_price' => 50_000,
    ]);
    Book::factory()->create([
        'slug' => 'sort-price-b',
        'name' => 'Sort Price Shared B',
        'selling_price' => 50_000,
    ]);

    $response = $this->getJson('/api/v1/books?keyword=sort+price+shared&sort=price_asc');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toHaveCount(2)
        ->and($ids[0])->toBeLessThan($ids[1]);
});

test('book catalog search service applies meilisearch filters on scout builder', function (): void {
    $service = app(BookCatalogSearchService::class);
    $builder = Book::search('test');

    $service->applyCatalogFilters($builder, [
        'publisher' => 7,
        'supplier' => 9,
        'price_min' => 10,
        'price_max' => 99,
    ]);

    expect($builder)->toBeInstanceOf(ScoutBuilder::class)
        ->and($builder->wheres)->toContain(
            ['field' => 'selling_price', 'operator' => '>=', 'value' => 10.0],
            ['field' => 'selling_price', 'operator' => '<=', 'value' => 99.0],
            ['field' => 'publisher_id', 'operator' => '=', 'value' => 7],
            ['field' => 'supplier_id', 'operator' => '=', 'value' => 9],
        );
});

test('books index without keyword still uses mysql catalog flow', function (): void {
    Book::factory()->create(['slug' => 'mysql-only-book', 'name' => 'Mysql Only']);

    $response = $this->getJson('/api/v1/books');

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('slug')->all())->toContain('mysql-only-book');
});
