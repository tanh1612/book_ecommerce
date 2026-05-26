<?php

use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentMethod;
use App\Enums\Order\PaymentStatus;
use App\Enums\Review\ReviewStatus;
use App\Filament\Imports\BookImporter;
use App\Jobs\Search\SyncBookToMeilisearch;
use App\Models\Account;
use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Publisher;
use App\Models\Review;
use App\Models\ShippingMethod;
use App\Models\Supplier;
use App\Services\Search\BookMeilisearchSyncDispatcher;
use Filament\Actions\Imports\Jobs\ImportCsv;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Scout\EngineManager;
use Tests\Support\RecordingScoutEngine;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Queue::fake();
});

function bindRecordingScoutEngineForTriggers(): RecordingScoutEngine
{
    $engine = new RecordingScoutEngine;
    $manager = app(EngineManager::class);
    $manager->forgetDrivers();
    $manager->extend('recording', fn (): RecordingScoutEngine => $engine);
    config([
        'scout.driver' => 'recording',
        'scout.queue' => false,
    ]);

    return $engine;
}

test('book detail save dispatches meilisearch sync job', function (): void {
    $book = Book::factory()->create();
    $detail = $book->detail;

    $detail->update(['description' => 'New description for search']);

    Queue::assertPushed(SyncBookToMeilisearch::class, fn (SyncBookToMeilisearch $job): bool => $job->bookId === $book->id);
});

test('book author attach dispatches meilisearch sync job', function (): void {
    $book = Book::factory()->create();
    $author = Author::factory()->create();

    $book->authors()->attach($author);

    Queue::assertPushed(SyncBookToMeilisearch::class, fn (SyncBookToMeilisearch $job): bool => $job->bookId === $book->id);
});

test('author name change dispatches sync for all linked books once each', function (): void {
    $author = Author::factory()->create(['name' => 'Old Name']);
    $bookA = Book::factory()->create();
    $bookB = Book::factory()->create();
    $author->books()->attach([$bookA->id, $bookB->id]);

    Queue::fake();

    $author->update(['name' => 'New Author Name']);

    Queue::assertPushed(SyncBookToMeilisearch::class, 2);
    Queue::assertPushed(SyncBookToMeilisearch::class, fn (SyncBookToMeilisearch $job): bool => $job->bookId === $bookA->id);
    Queue::assertPushed(SyncBookToMeilisearch::class, fn (SyncBookToMeilisearch $job): bool => $job->bookId === $bookB->id);
});

test('book category attach dispatches meilisearch sync job', function (): void {
    $book = Book::factory()->create();
    $category = Category::factory()->create();

    $book->categories()->attach($category);

    Queue::assertPushed(SyncBookToMeilisearch::class, fn (SyncBookToMeilisearch $job): bool => $job->bookId === $book->id);
});

test('detaching category dispatches reindex and document drops removed category', function (): void {
    $book = Book::factory()->create();
    $removed = Category::factory()->create();
    $remaining = Category::factory()->create();
    $book->categories()->attach([$removed->id, $remaining->id]);

    Queue::fake();

    $book->categories()->detach($removed->id);

    Queue::assertPushed(SyncBookToMeilisearch::class, fn (SyncBookToMeilisearch $job): bool => $job->bookId === $book->id);

    $engine = bindRecordingScoutEngineForTriggers();
    (new SyncBookToMeilisearch($book->id))->handle();

    expect($engine->lastDocument()['category_ids'] ?? null)->toBe([$remaining->id]);
});

test('author delete dispatches reindex and document drops removed author', function (): void {
    $book = Book::factory()->create();
    $author = Author::factory()->create(['name' => 'Gone Author']);
    $book->authors()->attach($author);

    Queue::fake();

    $author->delete();

    Queue::assertPushed(SyncBookToMeilisearch::class, fn (SyncBookToMeilisearch $job): bool => $job->bookId === $book->id);

    $engine = bindRecordingScoutEngineForTriggers();
    (new SyncBookToMeilisearch($book->id))->handle();

    $document = $engine->lastDocument();

    expect($document['author_ids'] ?? null)->toBe([])
        ->and($document['author_names'] ?? null)->toBe([]);
});

test('publisher delete dispatches reindex and document clears publisher_id', function (): void {
    $publisher = Publisher::factory()->create();
    $book = Book::factory()->create(['publisher_id' => $publisher->id]);

    Queue::fake();

    $publisher->delete();

    Queue::assertPushed(SyncBookToMeilisearch::class, fn (SyncBookToMeilisearch $job): bool => $job->bookId === $book->id);

    $engine = bindRecordingScoutEngineForTriggers();
    (new SyncBookToMeilisearch($book->id))->handle();

    expect($book->fresh()->publisher_id)->toBeNull()
        ->and($engine->lastDocument())->not->toBeNull()
        ->and($engine->lastDocument()['publisher_id'])->toBeNull();
});

test('inventory change dispatches meilisearch sync job', function (): void {
    $book = Book::factory()->create();
    $inventory = Inventory::factory()->create([
        'book_id' => $book->id,
        'quantity' => 5,
        'reserved_quantity' => 0,
    ]);

    Queue::fake();

    $inventory->update(['quantity' => 10]);

    Queue::assertPushed(SyncBookToMeilisearch::class, fn (SyncBookToMeilisearch $job): bool => $job->bookId === $book->id);
});

function meilisearchSyncTestReview(Book $book): Review
{
    $account = Account::factory()->create();
    $shipping = ShippingMethod::query()->create([
        'name' => 'Test ship',
        'description' => null,
        'is_active' => true,
    ]);
    $order = Order::query()->create([
        'account_id' => $account->id,
        'shipping_method_id' => $shipping->id,
        'total_amount' => 100_000,
        'shipping_fee' => 0,
        'final_amount' => 100_000,
        'shipping_name' => 'Test',
        'shipping_phone' => '0900000000',
        'shipping_address' => 'Addr',
        'payment_method' => PaymentMethod::COD,
        'payment_status' => PaymentStatus::PAID,
        'current_status' => OrderStatus::COMPLETED,
    ]);
    $orderItem = OrderItem::query()->create([
        'order_id' => $order->id,
        'book_id' => $book->id,
        'promotion_id' => null,
        'price' => 100_000,
        'quantity' => 1,
        'total_price' => 100_000,
        'discount_amount' => 0,
        'is_reviewed' => true,
    ]);

    return Review::query()->create([
        'account_id' => $account->id,
        'book_id' => $book->id,
        'order_item_id' => $orderItem->id,
        'rating' => 5,
        'comment' => 'Test review',
        'status' => ReviewStatus::APPROVED,
    ]);
}

test('review save dispatches meilisearch sync for current and previous book', function (): void {
    $oldBook = Book::factory()->create();
    $newBook = Book::factory()->create();
    $review = meilisearchSyncTestReview($oldBook);

    Queue::fake();

    $review->update(['book_id' => $newBook->id]);

    Queue::assertPushed(SyncBookToMeilisearch::class, fn (SyncBookToMeilisearch $job): bool => $job->bookId === $newBook->id);
    Queue::assertPushed(SyncBookToMeilisearch::class, fn (SyncBookToMeilisearch $job): bool => $job->bookId === $oldBook->id);
});

test('meilisearch sync dispatcher deduplicates repeated committed book ids', function (): void {
    $dispatcher = app(BookMeilisearchSyncDispatcher::class);

    DB::transaction(function () use ($dispatcher): void {
        $dispatcher->dispatch(1);
        $dispatcher->dispatch(1);
        $dispatcher->dispatch(2);
    });

    Queue::assertPushed(SyncBookToMeilisearch::class, 2);
});

test('meilisearch sync dispatcher deduplicates across separate container resolves in one transaction', function (): void {
    DB::transaction(function (): void {
        app(BookMeilisearchSyncDispatcher::class)->dispatch(42);
        app(BookMeilisearchSyncDispatcher::class)->dispatch(42);
    });

    Queue::assertPushed(SyncBookToMeilisearch::class, 1);
    Queue::assertPushed(SyncBookToMeilisearch::class, fn (SyncBookToMeilisearch $job): bool => $job->bookId === 42);
});

test('meilisearch sync dispatcher recovers after transaction rollback', function (): void {
    DB::transaction(function (): void {
        app(BookMeilisearchSyncDispatcher::class)->dispatch(6);
    });

    Queue::assertPushed(SyncBookToMeilisearch::class, fn (SyncBookToMeilisearch $job): bool => $job->bookId === 6);

    Queue::fake();

    try {
        DB::transaction(function (): void {
            app(BookMeilisearchSyncDispatcher::class)->dispatch(7);
            throw new RuntimeException('force rollback');
        });
    } catch (RuntimeException) {
        //
    }

    Queue::assertNothingPushed();

    DB::transaction(function (): void {
        app(BookMeilisearchSyncDispatcher::class)->dispatch(7);
    });

    Queue::assertPushed(SyncBookToMeilisearch::class, 1);
    Queue::assertPushed(SyncBookToMeilisearch::class, fn (SyncBookToMeilisearch $job): bool => $job->bookId === 7);
});

test('meilisearch sync dispatcher drops work from rolled back nested transactions', function (): void {
    DB::transaction(function (): void {
        app(BookMeilisearchSyncDispatcher::class)->dispatch(8);

        try {
            DB::transaction(function (): void {
                app(BookMeilisearchSyncDispatcher::class)->dispatch(9);
                throw new RuntimeException('rollback nested work');
            });
        } catch (RuntimeException) {
            //
        }
    });

    Queue::assertPushed(SyncBookToMeilisearch::class, 1);
    Queue::assertPushed(SyncBookToMeilisearch::class, fn (SyncBookToMeilisearch $job): bool => $job->bookId === 8);
    Queue::assertNotPushed(SyncBookToMeilisearch::class, fn (SyncBookToMeilisearch $job): bool => $job->bookId === 9);
});

test('book import row dispatches a single meilisearch sync job', function (): void {
    $admin = Account::factory()->create();
    $supplier = Supplier::factory()->create(['name' => 'Import Supplier']);
    $author = Author::factory()->create(['name' => 'Import Author']);
    $parent = Category::factory()->create(['name' => 'Import Root']);
    $child = Category::factory()->child($parent)->create(['name' => 'Import Leaf']);
    $breadcrumb = $child->fresh(['parent'])->getBreadcrumb();

    $storage = Mockery::mock(\App\Services\Media\BookImageStorageService::class);
    $storage->shouldReceive('uploadImageFromUrl')
        ->andReturn('book_ecommerce/books/1/img-1-test');
    $storage->shouldReceive('deliveryUrlFromPublicId')->andReturn('https://res.cloudinary.test/img.jpg');
    $storage->shouldReceive('thumbnailDeliveryUrlFromDeliveryUrl')->andReturn('https://res.cloudinary.test/thumb.jpg');
    $storage->shouldReceive('deleteByPublicId');
    $storage->shouldReceive('deleteEmptyBookFolderForSlug');
    $storage->shouldReceive('normalizeSortOrdersForBook');
    app()->instance(\App\Services\Media\BookImageStorageService::class, $storage);

    $import = Import::query()->create([
        'file_name' => 'books.csv',
        'file_path' => 'imports/test-books.csv',
        'importer' => BookImporter::class,
        'total_rows' => 1,
        'user_id' => $admin->id,
    ]);

    $importer = new BookImporter($import, [
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
    ], []);

    $importer([
        'name' => 'Imported Meilisearch Book',
        'sku' => 'IMPORT-MEILI-'.uniqid(),
        'original_price' => '150000',
        'selling_price' => null,
        'thumbnail_url' => 'https://cdn.example.com/import.jpg',
        'supplier' => $supplier->name,
        'publisher' => null,
        'authors' => $author->name,
        'categories' => $breadcrumb,
        'description' => 'Import description',
        'language' => null,
        'format' => null,
        'num_pages' => null,
        'weight' => null,
        'dimensions' => null,
        'publication_year' => null,
        'translator' => null,
    ]);

    $book = Book::query()->where('name', 'Imported Meilisearch Book')->first();

    expect($book)->not->toBeNull();

    Queue::assertPushed(SyncBookToMeilisearch::class, 1);
    Queue::assertPushed(SyncBookToMeilisearch::class, fn (SyncBookToMeilisearch $job): bool => $job->bookId === $book->id);
});

test('book import chunk enqueues sync only for successful rows', function (): void {
    $admin = Account::factory()->create();
    $supplier = Supplier::factory()->create(['name' => 'Chunk Supplier']);
    $importCategory = Category::factory()->create(['name' => 'Chunk Import Category']);
    $importCategoryBreadcrumb = $importCategory->getBreadcrumb();

    $storage = Mockery::mock(\App\Services\Media\BookImageStorageService::class);
    $storage->shouldReceive('uploadImageFromUrl')
        ->once()
        ->with('https://cdn.example.com/success.jpg', Mockery::type('int'), 1)
        ->andReturnUsing(fn (string $url, int $bookId, int $sortOrder): string => "book_ecommerce/books/{$bookId}/img-{$sortOrder}-success");
    $storage->shouldReceive('uploadImageFromUrl')
        ->once()
        ->with('https://cdn.example.com/failure.jpg', Mockery::type('int'), 1)
        ->andThrow(new RuntimeException('Cloudinary unavailable'));
    $storage->shouldReceive('deliveryUrlFromPublicId')->byDefault()->andReturn('https://res.cloudinary.test/img.jpg');
    $storage->shouldReceive('thumbnailDeliveryUrlFromDeliveryUrl')->byDefault()->andReturn('https://res.cloudinary.test/thumb.jpg');
    $storage->shouldReceive('deleteByPublicId')->byDefault();
    $storage->shouldReceive('deleteEmptyBookFolderForSlug')->byDefault();
    $storage->shouldReceive('normalizeSortOrdersForBook')->byDefault();
    app()->instance(\App\Services\Media\BookImageStorageService::class, $storage);

    $import = Import::query()->create([
        'file_name' => 'chunk-books.csv',
        'file_path' => 'imports/chunk-books.csv',
        'importer' => BookImporter::class,
        'total_rows' => 2,
        'user_id' => $admin->id,
    ]);

    $columnMap = [
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
    $defaultRow = [
        'selling_price' => null,
        'supplier' => $supplier->name,
        'publisher' => null,
        'authors' => null,
        'categories' => $importCategoryBreadcrumb,
        'description' => null,
        'language' => null,
        'format' => null,
        'num_pages' => null,
        'weight' => null,
        'dimensions' => null,
        'publication_year' => null,
        'translator' => null,
    ];
    $rows = [
        array_merge($defaultRow, [
            'name' => 'Successful chunk book',
            'sku' => 'CHUNK-SUCCESS',
            'original_price' => '150000',
            'thumbnail_url' => 'https://cdn.example.com/success.jpg',
        ]),
        array_merge($defaultRow, [
            'name' => 'Failed chunk book',
            'sku' => 'CHUNK-FAILURE',
            'original_price' => '150000',
            'thumbnail_url' => 'https://cdn.example.com/failure.jpg',
        ]),
    ];

    (new ImportCsv($import, $rows, $columnMap))->handle();

    $successfulBook = Book::query()->where('sku', 'CHUNK-SUCCESS')->firstOrFail();

    expect(Book::query()->where('sku', 'CHUNK-FAILURE')->exists())->toBeFalse();
    Queue::assertPushed(SyncBookToMeilisearch::class, 1);
    Queue::assertPushed(SyncBookToMeilisearch::class, fn (SyncBookToMeilisearch $job): bool => $job->bookId === $successfulBook->id);
});
