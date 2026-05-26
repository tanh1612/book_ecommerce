<?php

use App\Models\Book;
use App\Models\Category;
use App\Services\Catalog\BookCategoryAssignmentService;
use App\Services\Catalog\CatalogCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function categoryAssignmentService(): BookCategoryAssignmentService
{
    return app(BookCategoryAssignmentService::class);
}

test('syncCategories replaces single category with another', function (): void {
    $old = Category::factory()->create();
    $new = Category::factory()->create();
    $book = Book::factory()->create();
    $book->categories()->attach($old);

    categoryAssignmentService()->syncCategories($book, [$new->id]);

    $ids = $book->fresh()->categories()->pluck('categories.id')->all();

    expect($ids)->toBe([$new->id]);
});

test('syncCategories replaces multiple categories with one', function (): void {
    $first = Category::factory()->create();
    $second = Category::factory()->create();
    $replacement = Category::factory()->create();
    $book = Book::factory()->create();
    $book->categories()->attach([$first->id, $second->id]);

    categoryAssignmentService()->syncCategories($book, [$replacement->id]);

    expect($book->fresh()->categories()->pluck('categories.id')->all())
        ->toBe([$replacement->id]);
});

test('syncCategories rejects empty array and keeps existing links', function (): void {
    $category = Category::factory()->create();
    $book = Book::factory()->create();
    $book->categories()->attach($category);

    expect(fn () => categoryAssignmentService()->syncCategories($book, []))
        ->toThrow(ValidationException::class);

    expect($book->fresh()->categories()->whereKey($category->id)->exists())->toBeTrue();
});

test('detachCategories rejects removing the last category', function (): void {
    $category = Category::factory()->create();
    $book = Book::factory()->create();
    $book->categories()->attach($category);

    expect(fn () => categoryAssignmentService()->detachCategories($book, [$category->id]))
        ->toThrow(ValidationException::class);

    expect($book->fresh()->categories()->whereKey($category->id)->exists())->toBeTrue();
});

test('detachCategories allows removing one category when another remains', function (): void {
    $primary = Category::factory()->create();
    $secondary = Category::factory()->create();
    $book = Book::factory()->create();
    $book->categories()->attach([$primary->id, $secondary->id]);

    categoryAssignmentService()->detachCategories($book, [$primary->id]);

    expect($book->fresh()->categories()->pluck('categories.id')->all())
        ->toContain($secondary->id)
        ->not->toContain($primary->id);
});

test('attachCategories adds categories without removing existing ones', function (): void {
    $existing = Category::factory()->create();
    $extra = Category::factory()->create();
    $book = Book::factory()->create();
    $book->categories()->attach($existing);

    categoryAssignmentService()->attachCategories($book, [$extra->id]);

    expect($book->fresh()->categories()->pluck('categories.id')->all())
        ->toEqualCanonicalizing([$existing->id, $extra->id]);
});

test('bulkAttachCategoryToBooks attaches one category to multiple books', function (): void {
    $category = Category::factory()->create();
    $existing = Category::factory()->create();
    $books = Book::factory()->count(2)->create();

    foreach ($books as $book) {
        $book->categories()->attach($existing);
    }

    categoryAssignmentService()->bulkAttachCategoryToBooks($books, $category->id);

    foreach ($books as $book) {
        expect($book->fresh()->categories()->whereKey($category->id)->exists())->toBeTrue();
    }
});

test('failed syncCategories rolls back and leaves prior categories intact', function (): void {
    $existing = Category::factory()->create();
    $book = Book::factory()->create();
    $book->categories()->attach($existing);

    expect(fn () => categoryAssignmentService()->syncCategories($book, [$existing->id, 9_999_999]))
        ->toThrow(ValidationException::class);

    expect($book->fresh()->categories()->pluck('categories.id')->all())->toBe([$existing->id]);
});

test('bulkDetachCategoryFromBooks is atomic when any book would lose its last category', function (): void {
    $owner = Category::factory()->create();
    $other = Category::factory()->create();

    $onlyOwner = Book::factory()->create();
    $onlyOwner->categories()->attach($owner);

    $withSpare = Book::factory()->create();
    $withSpare->categories()->attach([$owner->id, $other->id]);

    expect(fn () => categoryAssignmentService()->bulkDetachCategoryFromBooks([$onlyOwner, $withSpare], $owner->id))
        ->toThrow(ValidationException::class);

    expect($onlyOwner->fresh()->categories()->whereKey($owner->id)->exists())->toBeTrue()
        ->and($withSpare->fresh()->categories()->whereKey($owner->id)->exists())->toBeTrue();
});

test('book category assignment uses row lock inside transaction', function (): void {
    $source = file_get_contents(app_path('Services/Catalog/BookCategoryAssignmentService.php'));

    expect($source)->toContain('lockForUpdate()')
        ->and($source)->toContain('DB::transaction');
});

test('book category assignment locks target categories in the mutation transaction', function (): void {
    $source = file_get_contents(app_path('Services/Catalog/BookCategoryAssignmentService.php'));

    expect($source)->toContain('$this->lockCategories($categoryIdsToLock);')
        ->and($source)->toContain('private function lockCategories(array $categoryIds): void')
        ->and($source)->toContain("->lockForUpdate()\n            ->pluck('id');");
});

test('rolled back category mutation does not invalidate cached book detail', function (): void {
    $book = Book::factory()->create();
    $category = Category::factory()->create();
    $key = app(CatalogCacheService::class)->bookDetailCacheKey($book->slug);

    Cache::put($key, 'cached-detail', 900);

    try {
        DB::transaction(function () use ($book, $category, $key): void {
            $book->categories()->attach($category);

            expect(Cache::has($key))->toBeTrue();

            throw new \RuntimeException('rollback category mutation');
        });
    } catch (\RuntimeException $exception) {
        expect($exception->getMessage())->toBe('rollback category mutation');
    }

    expect(Cache::has($key))->toBeTrue()
        ->and($book->fresh()->categories()->whereKey($category->id)->exists())->toBeFalse();
});

test('committed category mutation invalidates cached book detail', function (): void {
    $book = Book::factory()->create();
    $category = Category::factory()->create();
    $key = app(CatalogCacheService::class)->bookDetailCacheKey($book->slug);

    Cache::put($key, 'cached-detail', 900);

    DB::transaction(function () use ($book, $category, $key): void {
        $book->categories()->attach($category);

        expect(Cache::has($key))->toBeTrue();
    });

    expect(Cache::has($key))->toBeFalse();
});
