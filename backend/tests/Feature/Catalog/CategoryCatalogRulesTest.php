<?php

use App\Models\Book;
use App\Models\Category;
use App\Models\Inventory;
use App\Services\Catalog\BookCategoryAssignmentService;
use App\Services\Catalog\CatalogCacheService;
use App\Services\Catalog\CategoryDeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('books filters include all root categories', function (): void {
    Category::factory()->create(['slug' => 'filters-root-a', 'name' => 'Filters Root A']);
    Category::factory()->create(['slug' => 'filters-root-b', 'name' => 'Filters Root B']);

    $response = $this->getJson('/api/v1/books/filters');

    $response->assertOk();
    $slugs = collect($response->json('data.categories'))->pluck('slug')->all();

    expect($slugs)->toContain('filters-root-a', 'filters-root-b');
});

test('books index filters by parent category slug including descendants', function (): void {
    $parent = Category::factory()->create(['slug' => 'filter-parent-cat', 'name' => 'Filter Parent']);
    $child = Category::factory()->child($parent)->create(['slug' => 'filter-child-cat', 'name' => 'Filter Child']);

    $hit = Book::factory()->create(['slug' => 'filter-parent-hit', 'name' => 'Parent Branch Hit']);
    $hit->categories()->attach($child);

    $miss = Book::factory()->create(['slug' => 'filter-parent-miss', 'name' => 'Outside Branch']);
    $other = Category::factory()->create(['slug' => 'other-branch', 'name' => 'Other Branch']);
    $miss->categories()->attach($other);

    $response = $this->getJson('/api/v1/books?category=filter-parent-cat');

    $response->assertOk();
    $slugs = collect($response->json('data'))->pluck('slug')->all();

    expect($slugs)->toContain('filter-parent-hit')->not->toContain('filter-parent-miss');
});

test('book detail includes all linked categories', function (): void {
    $category = Category::factory()->create(['slug' => 'detail-cat', 'name' => 'Detail Category']);
    $book = Book::factory()->create(['slug' => 'detail-cat-book']);
    $book->categories()->attach($category);

    $this->getJson('/api/v1/books/detail-cat-book')
        ->assertOk()
        ->assertJsonPath('data.categories.0.slug', 'detail-cat');
});

test('cannot delete category that has children', function (): void {
    $parent = Category::factory()->create();
    Category::factory()->child($parent)->create();

    expect(fn () => $parent->delete())->toThrow(ValidationException::class);
    expect(Category::query()->whereKey($parent->id)->exists())->toBeTrue();
});

test('cannot delete category linked to books', function (): void {
    $category = Category::factory()->create();
    $book = Book::factory()->create();
    $book->categories()->attach($category);

    expect(fn () => $category->delete())->toThrow(ValidationException::class);
    expect(Category::query()->whereKey($category->id)->exists())->toBeTrue();
});

test('can delete empty category without children', function (): void {
    $category = Category::factory()->create();

    $category->delete();

    expect(Category::query()->whereKey($category->id)->exists())->toBeFalse();
});

test('category deletion service deletes an empty category', function (): void {
    $category = Category::factory()->create();

    app(CategoryDeletionService::class)->delete($category);

    expect(Category::query()->whereKey($category->id)->exists())->toBeFalse();
});

test('category deletion service rejects a category linked to books', function (): void {
    $category = Category::factory()->create();
    $book = Book::factory()->create();
    $book->categories()->attach($category);

    expect(fn () => app(CategoryDeletionService::class)->delete($category))
        ->toThrow(ValidationException::class);

    expect(Category::query()->whereKey($category->id)->exists())->toBeTrue();
});

test('category deletion service locks category row during deletion', function (): void {
    $source = file_get_contents(app_path('Services/Catalog/CategoryDeletionService.php'));

    expect($source)->toContain('DB::transaction')
        ->and($source)->toContain('->lockForUpdate()')
        ->and($source)->toContain('$lockedCategory->delete()');
});

test('cannot detach last category from book via assignment service', function (): void {
    $category = Category::factory()->create();
    $book = Book::factory()->create();
    $book->categories()->attach($category);

    $service = app(BookCategoryAssignmentService::class);

    expect(fn () => $service->detachCategories($book, [$category->id]))
        ->toThrow(ValidationException::class);

    expect($book->categories()->whereKey($category->id)->exists())->toBeTrue();
});

test('category can link multiple books at once', function (): void {
    $category = Category::factory()->create();
    $books = Book::factory()->count(2)->create();

    foreach ($books as $book) {
        $book->categories()->attach(Category::factory()->create());
    }

    $category->books()->attach($books->pluck('id'));

    expect($category->books()->pluck('books.id')->all())
        ->toEqualCanonicalizing($books->pluck('id')->all());
});

test('can detach category when book has another category via assignment service', function (): void {
    $primary = Category::factory()->create(['name' => 'Primary Cat']);
    $secondary = Category::factory()->create(['name' => 'Secondary Cat']);
    $book = Book::factory()->create();
    $book->categories()->attach([$primary->id, $secondary->id]);

    app(BookCategoryAssignmentService::class)->detachCategories($book, [$primary->id]);

    expect($book->fresh()->categories()->pluck('categories.id')->all())
        ->toContain($secondary->id)
        ->not->toContain($primary->id);
});

test('books index accepts any existing category slug', function (): void {
    $category = Category::factory()->create(['slug' => 'any-cat-slug']);
    $book = Book::factory()->create(['slug' => 'any-cat-book']);
    $book->categories()->attach($category);

    $this->getJson('/api/v1/books?category=any-cat-slug')
        ->assertOk()
        ->assertJsonFragment(['slug' => 'any-cat-book']);
});

test('category update refreshes filters metadata cache', function (): void {
    $cache = app(CatalogCacheService::class);
    $key = $cache->filtersMetadataCacheKey();

    Cache::put($key, ['categories' => []], 3600);

    $category = Category::factory()->create(['name' => 'Filters Cache Name']);
    $category->update(['name' => 'Renamed Filters Category']);

    expect(Cache::has($key))->toBeFalse();
});

test('books index includes inactive and out of stock books when filtering by category', function (): void {
    $category = Category::factory()->create(['slug' => 'visibility-cat']);

    $inactive = Book::factory()->inactive()->create([
        'slug' => 'inactive-in-cat',
        'name' => 'Inactive In Cat',
    ]);
    $inactive->categories()->attach($category);

    $outOfStock = Book::factory()->create([
        'slug' => 'oos-in-cat',
        'name' => 'OOS In Cat',
    ]);
    $outOfStock->categories()->attach($category);
    Inventory::factory()->create([
        'book_id' => $outOfStock->id,
        'quantity' => 0,
        'reserved_quantity' => 0,
    ]);

    $response = $this->getJson('/api/v1/books?category=visibility-cat');

    $response->assertOk();
    $slugs = collect($response->json('data'))->pluck('slug')->all();

    expect($slugs)->toContain('inactive-in-cat', 'oos-in-cat');
});
