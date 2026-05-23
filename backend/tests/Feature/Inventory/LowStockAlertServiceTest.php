<?php

use App\Models\Book;
use App\Models\Inventory;
use App\Models\Warehouse;
use App\Services\Inventory\LowStockAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['inventory.low_stock_threshold' => 5]);
});

test('low stock service returns only active books with available stock between 1 and threshold', function (): void {
    $warehouse = Warehouse::factory()->create();

    $lowBook = Book::factory()->create(['is_active' => true]);
    $outBook = Book::factory()->create(['is_active' => true]);
    $okBook = Book::factory()->create(['is_active' => true]);
    $inactiveBook = Book::factory()->create(['is_active' => false]);

    Inventory::factory()->create([
        'book_id' => $lowBook->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 8,
        'reserved_quantity' => 5,
        'location_code' => 'LOW-01',
    ]);

    Inventory::factory()->create([
        'book_id' => $outBook->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 3,
        'reserved_quantity' => 3,
    ]);

    Inventory::factory()->create([
        'book_id' => $okBook->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 20,
        'reserved_quantity' => 0,
    ]);

    Inventory::factory()->create([
        'book_id' => $inactiveBook->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 2,
        'reserved_quantity' => 0,
    ]);

    $items = app(LowStockAlertService::class)->getLowStockItems();

    expect($items)->toHaveCount(1)
        ->and($items->first()->bookId)->toBe($lowBook->id)
        ->and($items->first()->availableStock)->toBe(3)
        ->and($items->first()->sku)->toBe($lowBook->sku)
        ->and($items->first()->locationCode)->toBe('LOW-01');
});

test('low stock service respects configurable threshold', function (): void {
    config(['inventory.low_stock_threshold' => 2]);

    $warehouse = Warehouse::factory()->create();
    $book = Book::factory()->create(['is_active' => true]);

    Inventory::factory()->create([
        'book_id' => $book->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 4,
        'reserved_quantity' => 1,
    ]);

    expect(app(LowStockAlertService::class)->getLowStockItems())->toHaveCount(0);

    config(['inventory.low_stock_threshold' => 3]);

    expect(app(LowStockAlertService::class)->getLowStockItems())->toHaveCount(1);
});

test('low stock set hash changes when inventory set changes', function (): void {
    $service = app(LowStockAlertService::class);
    $warehouse = Warehouse::factory()->create();
    $bookA = Book::factory()->create(['is_active' => true]);
    $bookB = Book::factory()->create(['is_active' => true]);

    Inventory::factory()->create([
        'book_id' => $bookA->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 3,
        'reserved_quantity' => 1,
    ]);

    $hashA = $service->lowStockSetHash($service->lowStockInventoryIds());

    Inventory::factory()->create([
        'book_id' => $bookB->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 2,
        'reserved_quantity' => 0,
    ]);

    $hashB = $service->lowStockSetHash($service->lowStockInventoryIds());

    expect($hashA)->not->toBe($hashB);
});
