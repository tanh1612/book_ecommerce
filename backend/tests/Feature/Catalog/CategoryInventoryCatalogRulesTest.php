<?php

use App\Models\Book;
use App\Models\Inventory;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('inventory at zero sellable quantity sets book inactive', function (): void {
    $warehouse = Warehouse::factory()->create();
    $book = Book::factory()->create(['is_active' => true]);

    Inventory::factory()->create([
        'book_id' => $book->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 0,
        'reserved_quantity' => 0,
    ]);

    expect($book->refresh()->is_active)->toBeFalse();
});

test('book stays active when inventory still has sellable quantity', function (): void {
    $warehouse = Warehouse::factory()->create();
    $book = Book::factory()->create(['is_active' => true]);

    Inventory::factory()->create([
        'book_id' => $book->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 5,
        'reserved_quantity' => 2,
    ]);

    expect($book->refresh()->is_active)->toBeTrue();
});
