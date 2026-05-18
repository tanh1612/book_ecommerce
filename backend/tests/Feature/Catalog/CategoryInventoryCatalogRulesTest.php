<?php

use App\Models\Book;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('disabling parent category disables all descendants', function (): void {
    $parent = Category::factory()->create(['is_active' => true]);
    $child = Category::factory()->child($parent)->create(['is_active' => true]);
    $grand = Category::factory()->child($child)->create(['is_active' => true]);

    $parent->update(['is_active' => false]);

    expect($child->refresh()->is_active)->toBeFalse()
        ->and($grand->refresh()->is_active)->toBeFalse();
});

test('enabling parent category enables all descendants', function (): void {
    $parent = Category::factory()->create(['is_active' => false]);
    $child = Category::factory()->child($parent)->create(['is_active' => false]);

    $parent->update(['is_active' => true]);

    expect($child->refresh()->is_active)->toBeTrue();
});

test('cannot activate child category when ancestor is inactive', function (): void {
    $parent = Category::factory()->create(['is_active' => false]);
    $child = Category::factory()->child($parent)->create(['is_active' => false]);

    expect(fn () => $child->update(['is_active' => true]))
        ->toThrow(ValidationException::class);
});

test('toggling category is_active does not change linked book is_active', function (): void {
    $parent = Category::factory()->create(['is_active' => true]);
    $book = Book::factory()->create(['is_active' => true]);
    $book->categories()->attach($parent);

    $parent->update(['is_active' => false]);

    expect($book->refresh()->is_active)->toBeTrue();
});

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

test('book stays active when another warehouse still has sellable stock', function (): void {
    $w1 = Warehouse::factory()->create();
    $w2 = Warehouse::factory()->create();
    $book = Book::factory()->create(['is_active' => true]);

    Inventory::factory()->create([
        'book_id' => $book->id,
        'warehouse_id' => $w1->id,
        'quantity' => 3,
        'reserved_quantity' => 0,
    ]);
    Inventory::factory()->create([
        'book_id' => $book->id,
        'warehouse_id' => $w2->id,
        'quantity' => 0,
        'reserved_quantity' => 0,
    ]);

    expect($book->refresh()->is_active)->toBeTrue();
});
