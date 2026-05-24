<?php

use App\Enums\Account\AccountRole;
use App\Filament\Imports\AuthorImporter;
use App\Filament\Imports\BookImporter;
use App\Filament\Imports\InventoryImporter;
use App\Models\Account;
use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Publisher;
use App\Models\Supplier;
use App\Models\Warehouse;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(fn () => BookImporter::clearBreadcrumbCache());

function makeImportForBook(): Import
{
    $admin = Account::factory()->create([
        'role' => AccountRole::Admin->value,
    ]);

    return Import::query()->create([
        'file_name' => 'books.csv',
        'file_path' => 'imports/test-books.csv',
        'importer' => BookImporter::class,
        'total_rows' => 1,
        'user_id' => $admin->id,
    ]);
}

function bookColumnMapIdentity(): array
{
    return [
        'name' => 'name',
        'sku' => 'sku',
        'original_price' => 'original_price',
        'selling_price' => 'selling_price',
        'thumbnail_url' => 'thumbnail_url',
        'supplier' => 'supplier',
        'publisher' => 'publisher',
        'authors' => 'authors',
        'categories' => 'categories',
        'description' => 'description',
        'language' => 'language',
        'format' => 'format',
        'num_pages' => 'num_pages',
        'weight' => 'weight',
        'dimensions' => 'dimensions',
        'publication_year' => 'publication_year',
        'translator' => 'translator',
    ];
}

function makeImportForInventory(): Import
{
    $admin = Account::factory()->create([
        'role' => AccountRole::Admin->value,
    ]);

    return Import::query()->create([
        'file_name' => 'inventories.csv',
        'file_path' => 'imports/test-inventories.csv',
        'importer' => InventoryImporter::class,
        'total_rows' => 1,
        'user_id' => $admin->id,
    ]);
}

function inventoryColumnMapIdentity(): array
{
    return [
        'book_sku' => 'book_sku',
        'warehouse_id' => 'warehouse_id',
        'quantity' => 'quantity',
        'location_code' => 'location_code',
    ];
}

test('book importer creates book with detail relations when row is valid', function (): void {
    $supplier = Supplier::factory()->create(['name' => 'NCC Import Test']);
    $publisher = Publisher::factory()->create(['name' => 'NXB Import Test']);
    $author = Author::factory()->create(['name' => 'TG Import Test']);
    $parent = Category::factory()->create(['name' => 'RootCatImport']);
    $child = Category::factory()->child($parent)->create(['name' => 'LeafCatImport']);
    $breadcrumb = $child->fresh(['parent'])->getBreadcrumb();

    $import = makeImportForBook();
    $importer = new BookImporter($import, bookColumnMapIdentity(), []);

    $row = [
        'name' => 'Sách import test',
        'sku' => null,
        'original_price' => '120000',
        'selling_price' => null,
        'thumbnail_url' => null,
        'supplier' => $supplier->name,
        'publisher' => $publisher->name,
        'authors' => $author->name,
        'categories' => $breadcrumb,
        'description' => null,
        'language' => 'Tiếng Việt',
        'format' => 'Bìa mềm',
        'num_pages' => null,
        'weight' => null,
        'dimensions' => null,
        'publication_year' => null,
        'translator' => null,
    ];

    $importer($row);

    $book = \App\Models\Book::query()->where('name', 'Sách import test')->first();
    expect($book)->not->toBeNull()
        ->and($book->supplier_id)->toBe($supplier->id)
        ->and($book->publisher_id)->toBe($publisher->id)
        ->and($book->detail)->not->toBeNull()
        ->and($book->authors()->pluck('authors.id')->all())->toContain($author->id)
        ->and($book->categories()->pluck('categories.id')->all())->toContain($child->id);
});

test('book importer throws when author name is missing', function (): void {
    $supplier = Supplier::factory()->create(['name' => 'NCC Import Test 2']);

    $import = makeImportForBook();
    $importer = new BookImporter($import, bookColumnMapIdentity(), []);

    $row = [
        'name' => 'Sách không tác giả',
        'sku' => null,
        'original_price' => '50000',
        'selling_price' => null,
        'thumbnail_url' => null,
        'supplier' => $supplier->name,
        'publisher' => null,
        'authors' => 'Không Có Tác Giả Này',
        'categories' => null,
        'description' => null,
        'language' => null,
        'format' => null,
        'num_pages' => null,
        'weight' => null,
        'dimensions' => null,
        'publication_year' => null,
        'translator' => null,
    ];

    expect(fn () => $importer($row))->toThrow(RowImportFailedException::class);
});

test('book importer throws when publisher name does not exist', function (): void {
    $supplier = Supplier::factory()->create(['name' => 'NCC Import Test 3']);

    $import = makeImportForBook();
    $importer = new BookImporter($import, bookColumnMapIdentity(), []);

    $row = [
        'name' => 'Sách NXB sai',
        'sku' => null,
        'original_price' => '50000',
        'selling_price' => null,
        'thumbnail_url' => null,
        'supplier' => $supplier->name,
        'publisher' => 'NXB Không Tồn Tại XYZ',
        'authors' => null,
        'categories' => null,
        'description' => null,
        'language' => null,
        'format' => null,
        'num_pages' => null,
        'weight' => null,
        'dimensions' => null,
        'publication_year' => null,
        'translator' => null,
    ];

    expect(fn () => $importer($row))->toThrow(RowImportFailedException::class);
});

test('author importer rejects duplicate email', function (): void {
    Author::factory()->create(['email' => 'dup@example.com', 'name' => 'Existing']);

    $admin = Account::factory()->create(['role' => AccountRole::Admin->value]);
    $import = Import::query()->create([
        'file_name' => 'authors.csv',
        'file_path' => 'imports/test-authors.csv',
        'importer' => AuthorImporter::class,
        'total_rows' => 1,
        'user_id' => $admin->id,
    ]);

    $columnMap = ['name' => 'name', 'email' => 'email'];
    $importer = new AuthorImporter($import, $columnMap, []);

    $row = ['name' => 'New Author', 'email' => 'dup@example.com'];

    expect(fn () => $importer($row))->toThrow(ValidationException::class);
});

test('inventory importer creates inventory with import timestamp defaults', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-05-24 10:30:00'));

    $book = Book::factory()->create(['sku' => 'IMPORT-SKU-001']);
    $warehouse = Warehouse::factory()->create(['is_active' => true]);
    $import = makeImportForInventory();
    $importer = new InventoryImporter($import, inventoryColumnMapIdentity(), []);

    $importer([
        'book_sku' => $book->sku,
        'warehouse_id' => (string) $warehouse->id,
        'quantity' => '25',
        'location_code' => 'A01-03',
    ]);

    $inventory = Inventory::query()->where('book_id', $book->id)->first();

    expect($inventory)->not->toBeNull()
        ->and($inventory->warehouse_id)->toBe($warehouse->id)
        ->and($inventory->quantity)->toBe(25)
        ->and($inventory->sold_quantity)->toBe(0)
        ->and($inventory->reserved_quantity)->toBe(0)
        ->and($inventory->location_code)->toBe('A01-03')
        ->and($inventory->last_restocked_at?->format('Y-m-d H:i:s'))->toBe('2026-05-24 10:30:00');

    Carbon::setTestNow();
});

test('inventory importer restocks existing inventory without changing sold or reserved quantities', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-05-24 11:45:00'));

    $book = Book::factory()->create(['sku' => 'IMPORT-SKU-002']);
    $warehouse = Warehouse::factory()->create(['is_active' => true]);
    $inventory = Inventory::factory()->create([
        'book_id' => $book->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
        'sold_quantity' => 3,
        'reserved_quantity' => 2,
        'location_code' => 'OLD',
        'last_restocked_at' => Carbon::parse('2026-05-23 09:00:00'),
    ]);
    $import = makeImportForInventory();
    $importer = new InventoryImporter($import, inventoryColumnMapIdentity(), []);

    $importer([
        'book_sku' => $book->sku,
        'warehouse_id' => (string) $warehouse->id,
        'quantity' => '7',
        'location_code' => 'B02-04',
    ]);

    $inventory->refresh();

    expect($inventory->warehouse_id)->toBe($warehouse->id)
        ->and($inventory->quantity)->toBe(17)
        ->and($inventory->sold_quantity)->toBe(3)
        ->and($inventory->reserved_quantity)->toBe(2)
        ->and($inventory->location_code)->toBe('B02-04')
        ->and($inventory->last_restocked_at?->format('Y-m-d H:i:s'))->toBe('2026-05-24 11:45:00');

    Carbon::setTestNow();
});
