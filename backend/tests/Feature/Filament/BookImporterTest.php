<?php

use App\Enums\Account\AccountRole;
use App\Filament\Imports\BookImporter;
use App\Models\Account;
use App\Models\Book;
use App\Models\Category;
use App\Models\Supplier;
use App\Services\Media\BookImageStorageService;
use App\Support\Catalog\CategoryBreadcrumbIndex;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(fn () => BookImporter::clearBreadcrumbCache());

function makeBookImport(): Import
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

function bookImporterColumnMap(): array
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

function validBookImportRow(array $overrides = []): array
{
    return array_merge([
        'name' => 'Sach import test',
        'sku' => 'IMPORT-SKU-'.uniqid(),
        'original_price' => '120000',
        'selling_price' => null,
        'thumbnail_url' => 'https://cdn.example.com/a.jpg',
        'supplier' => 'NCC Import',
        'publisher' => null,
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
    ], $overrides);
}

function bindSuccessfulBookImageStorage(): void
{
    $storage = Mockery::mock(BookImageStorageService::class);
    $storage->shouldReceive('uploadImageFromUrl')
        ->byDefault()
        ->andReturnUsing(fn (string $url, int $bookId, int $sortOrder): string => "book_ecommerce/books/{$bookId}/img-{$sortOrder}-test");
    $storage->shouldReceive('deliveryUrlFromPublicId')
        ->byDefault()
        ->andReturnUsing(fn (string $publicId): string => 'https://res.cloudinary.test/'.$publicId.'.jpg');
    $storage->shouldReceive('thumbnailDeliveryUrlFromDeliveryUrl')
        ->byDefault()
        ->andReturnUsing(fn (string $deliveryUrl): string => $deliveryUrl);
    $storage->shouldReceive('deleteByPublicId')->byDefault();
    $storage->shouldReceive('deleteEmptyBookFolderForSlug')->byDefault();
    $storage->shouldReceive('normalizeSortOrdersForBook')->byDefault();

    app()->instance(BookImageStorageService::class, $storage);
}

test('book importer matches category after normalize', function (): void {
    bindSuccessfulBookImageStorage();

    $supplier = Supplier::factory()->create(['name' => 'NCC Import']);
    $parent = Category::factory()->create(['name' => 'RootCatNormalize']);
    $child = Category::factory()->child($parent)->create(['name' => 'Tieu su - Hoi ky']);
    $canonical = $child->fresh(['parent'])->getBreadcrumb();
    $looseCsv = str_replace('-', '–', strtoupper($canonical));

    $import = makeBookImport();
    $importer = new BookImporter($import, bookImporterColumnMap(), []);

    $importer(validBookImportRow([
        'supplier' => $supplier->name,
        'categories' => '  '.$looseCsv.'  ',
    ]));

    $book = Book::query()->where('name', 'Sach import test')->first();

    expect($book)->not->toBeNull()
        ->and($book->categories()->pluck('categories.id')->all())->toContain($child->id);
});

test('book importer fails for unknown category', function (): void {
    $supplier = Supplier::factory()->create(['name' => 'NCC Import']);

    $import = makeBookImport();
    $importer = new BookImporter($import, bookImporterColumnMap(), []);

    expect(fn () => $importer(validBookImportRow([
        'supplier' => $supplier->name,
        'categories' => 'Khong > Ton Tai',
    ])))->toThrow(RowImportFailedException::class);
});

test('book importer matches category by unique leaf name when breadcrumb is omitted', function (): void {
    bindSuccessfulBookImageStorage();

    $supplier = Supplier::factory()->create(['name' => 'NCC Import']);
    $parent = Category::factory()->create(['name' => 'Sach Phat Trien Ban Than']);
    $child = Category::factory()->child($parent)->create(['name' => 'Tam Ly - Ky Nang Song']);

    $import = makeBookImport();
    $importer = new BookImporter($import, bookImporterColumnMap(), []);

    $importer(validBookImportRow([
        'supplier' => $supplier->name,
        'categories' => 'Tam Ly - Ky Nang Song',
    ]));

    $book = Book::query()->where('name', 'Sach import test')->first();

    expect($book)->not->toBeNull()
        ->and($book->categories()->pluck('categories.id')->all())->toContain($child->id);
});

test('book importer fails for ambiguous category leaf name', function (): void {
    $firstParent = Category::factory()->create(['name' => 'First Root']);
    $secondParent = Category::factory()->create(['name' => 'Second Root']);
    Category::factory()->child($firstParent)->create(['name' => 'Shared Leaf']);
    Category::factory()->child($secondParent)->create(['name' => 'Shared Leaf']);

    $index = CategoryBreadcrumbIndex::buildFromDatabase();

    expect(fn () => $index->resolveCategoryId('Shared Leaf'))
        ->toThrow(RowImportFailedException::class, 'breadcrumb');
});

test('book importer fails when normalized category key is ambiguous', function (): void {
    $parent = Category::factory()->create(['name' => 'AmbiguousRoot']);
    $first = Category::factory()->child($parent)->create(['name' => 'Tieu su - Hoi ky']);
    Category::factory()->child($parent)->create(['name' => 'Tieu su – Hoi ky']);

    $index = CategoryBreadcrumbIndex::buildFromDatabase();

    expect(fn () => $index->resolveCategoryId($first->fresh(['parent'])->getBreadcrumb()))
        ->toThrow(RowImportFailedException::class);
});

test('book importer fails when thumbnail url is empty', function (): void {
    $supplier = Supplier::factory()->create(['name' => 'NCC Import']);

    $import = makeBookImport();
    $importer = new BookImporter($import, bookImporterColumnMap(), []);

    expect(fn () => $importer(validBookImportRow([
        'supplier' => $supplier->name,
        'thumbnail_url' => '   ',
    ])))->toThrow(ValidationException::class);
});

test('book importer creates active book and images when upload succeeds', function (): void {
    bindSuccessfulBookImageStorage();

    $supplier = Supplier::factory()->create(['name' => 'NCC Import']);
    $import = makeBookImport();
    $importer = new BookImporter($import, bookImporterColumnMap(), []);

    $importer(validBookImportRow([
        'supplier' => $supplier->name,
        'thumbnail_url' => 'https://cdn.example.com/one.jpg https://cdn.example.com/two.jpg',
    ]));

    $book = Book::query()->where('name', 'Sach import test')->first();

    expect($book)->not->toBeNull()
        ->and($book->is_active)->toBeTrue()
        ->and($book->images)->toHaveCount(2)
        ->and($book->images->pluck('sort_order')->all())->toBe([1, 2]);
});

test('book importer succeeds when at least one image uploads', function (): void {
    $storage = Mockery::mock(BookImageStorageService::class);
    $storage->shouldReceive('uploadImageFromUrl')
        ->once()
        ->with('https://cdn.example.com/fail.jpg', Mockery::type('int'), 1)
        ->andThrow(new RuntimeException('Cloudinary unavailable'));
    $storage->shouldReceive('uploadImageFromUrl')
        ->once()
        ->with('https://cdn.example.com/ok.jpg', Mockery::type('int'), 2)
        ->andReturnUsing(fn (string $url, int $bookId, int $sortOrder): string => "book_ecommerce/books/{$bookId}/img-{$sortOrder}-ok");
    $storage->shouldReceive('deliveryUrlFromPublicId')
        ->byDefault()
        ->andReturnUsing(fn (string $publicId): string => 'https://res.cloudinary.test/'.$publicId.'.jpg');
    $storage->shouldReceive('thumbnailDeliveryUrlFromDeliveryUrl')
        ->byDefault()
        ->andReturnUsing(fn (string $deliveryUrl): string => $deliveryUrl);
    $storage->shouldReceive('deleteByPublicId')->byDefault();
    $storage->shouldReceive('deleteEmptyBookFolderForSlug')->byDefault();
    $storage->shouldReceive('normalizeSortOrdersForBook')->byDefault();
    app()->instance(BookImageStorageService::class, $storage);

    $supplier = Supplier::factory()->create(['name' => 'NCC Import']);
    $import = makeBookImport();
    $importer = new BookImporter($import, bookImporterColumnMap(), []);

    $importer(validBookImportRow([
        'supplier' => $supplier->name,
        'thumbnail_url' => 'https://cdn.example.com/fail.jpg https://cdn.example.com/ok.jpg',
    ]));

    $book = Book::query()->where('name', 'Sach import test')->first();

    expect($book)->not->toBeNull()
        ->and($book->is_active)->toBeTrue()
        ->and($book->images)->toHaveCount(1)
        ->and($book->images->first()->sort_order)->toBe(2);
});

test('book importer fails when all image uploads fail', function (): void {
    $storage = Mockery::mock(BookImageStorageService::class);
    $storage->shouldReceive('uploadImageFromUrl')
        ->twice()
        ->andThrow(new RuntimeException('Cloudinary unavailable'));
    $storage->shouldReceive('deleteByPublicId')->byDefault();
    $storage->shouldReceive('deleteEmptyBookFolderForSlug')->byDefault();
    $storage->shouldReceive('normalizeSortOrdersForBook')->byDefault();
    app()->instance(BookImageStorageService::class, $storage);

    $supplier = Supplier::factory()->create(['name' => 'NCC Import']);
    $import = makeBookImport();
    $importer = new BookImporter($import, bookImporterColumnMap(), []);

    expect(fn () => $importer(validBookImportRow([
        'supplier' => $supplier->name,
        'thumbnail_url' => 'https://cdn.example.com/a.jpg https://cdn.example.com/b.jpg',
    ])))->toThrow(RowImportFailedException::class);

    expect(Book::query()->where('name', 'Sach import test')->exists())->toBeFalse();
});

test('book importer uses fresh category index per importer instance', function (): void {
    bindSuccessfulBookImageStorage();

    $supplier = Supplier::factory()->create(['name' => 'NCC Import']);

    $firstImport = makeBookImport();
    $firstImporter = new BookImporter($firstImport, bookImporterColumnMap(), []);
    $firstImporter(validBookImportRow(['supplier' => $supplier->name]));

    $parent = Category::factory()->create(['name' => 'LateRoot']);
    $child = Category::factory()->child($parent)->create(['name' => 'LateChild']);

    $secondImport = makeBookImport();
    $secondImporter = new BookImporter($secondImport, bookImporterColumnMap(), []);
    $secondImporter(validBookImportRow([
        'name' => 'Second import book',
        'supplier' => $supplier->name,
        'categories' => $child->fresh(['parent'])->getBreadcrumb(),
    ]));

    $book = Book::query()->where('name', 'Second import book')->firstOrFail();

    expect($book->categories()->pluck('categories.id')->all())->toContain($child->id);
});

test('import image public id uses book id not book slug', function (): void {
    $book = Book::factory()->create([
        'slug' => str_repeat('very-long-book-slug-segment', 8),
        'is_active' => false,
    ]);

    $publicId = app(BookImageStorageService::class)->newBookImagePublicIdForBook($book->id, 2);

    expect($publicId)->toStartWith('book_ecommerce/books/'.$book->id.'/img-2-')
        ->and($publicId)->not->toContain($book->slug);
});
