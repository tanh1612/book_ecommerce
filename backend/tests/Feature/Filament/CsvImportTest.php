<?php

use App\Enums\Account\AccountRole;
use App\Filament\Imports\AuthorImporter;
use App\Filament\Imports\BookImporter;
use App\Models\Account;
use App\Models\Author;
use App\Models\Category;
use App\Models\Publisher;
use App\Models\Supplier;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
