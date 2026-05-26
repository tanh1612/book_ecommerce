<?php

use App\Filament\Resources\AuthorResource\Pages\ListAuthors;
use App\Filament\Resources\BookResource\Pages\ListBooks;
use App\Filament\Resources\CategoryResource\Pages\ListCategories;
use App\Filament\Resources\PublisherResource\Pages\ListPublishers;
use App\Filament\Resources\SupplierResource\Pages\ListSuppliers;
use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Publisher;
use App\Models\Supplier;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('book list all tab defers and counts books', function (): void {
    Book::factory()->count(2)->create();

    assertAllTabCount(ListBooks::class, 2);
});

test('category list all tab defers and counts categories', function (): void {
    Category::factory()->count(3)->create();

    assertAllTabCount(ListCategories::class, 3);
});

test('author list all tab defers and counts authors', function (): void {
    Author::factory()->count(4)->create();

    assertAllTabCount(ListAuthors::class, 4);
});

test('publisher list all tab defers and counts publishers', function (): void {
    Publisher::factory()->count(2)->create();

    assertAllTabCount(ListPublishers::class, 2);
});

test('supplier list all tab defers and counts suppliers', function (): void {
    Supplier::factory()->count(5)->create();

    assertAllTabCount(ListSuppliers::class, 5);
});

/**
 * @param  class-string<ListBooks|ListCategories|ListAuthors|ListPublishers|ListSuppliers>  $pageClass
 */
function assertAllTabCount(string $pageClass, int $expectedCount): void
{
    $tabs = app($pageClass)->getTabs();

    expect($tabs)->toHaveKey('all')
        ->and($tabs['all'])->toBeInstanceOf(Tab::class)
        ->and($tabs['all']->getLabel())->toBe('Tất cả')
        ->and($tabs['all']->getBadge())->toBe($expectedCount)
        ->and($tabs['all']->getBadgeColor())->toBe('success')
        ->and($tabs['all']->isBadgeDeferred())->toBeTrue();
}
