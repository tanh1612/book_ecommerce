<?php

use App\Enums\Account\AccountRole;
use App\Filament\Resources\InventoryResource\Pages\ListInventories;
use App\Models\Account;
use App\Models\Book;
use App\Models\Inventory;
use App\Models\Warehouse;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutMiddleware(VerifyCsrfToken::class);
    config(['inventory.low_stock_threshold' => 5]);
});

test('inventory low stock filter uses configurable threshold', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);
    $warehouse = Warehouse::factory()->create();

    $lowBook = Book::factory()->create(['is_active' => true]);
    $okBook = Book::factory()->create(['is_active' => true]);

    Inventory::factory()->create([
        'book_id' => $lowBook->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 6,
        'reserved_quantity' => 2,
    ]);

    Inventory::factory()->create([
        'book_id' => $okBook->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 20,
        'reserved_quantity' => 0,
    ]);

    Livewire::actingAs($admin)
        ->test(ListInventories::class)
        ->filterTable('stock_status', 'low')
        ->assertCanSeeTableRecords(Inventory::query()->where('book_id', $lowBook->id)->get())
        ->assertCanNotSeeTableRecords(Inventory::query()->where('book_id', $okBook->id)->get());
});

test('inventory low stock tab scopes records to low stock items', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);
    $warehouse = Warehouse::factory()->create();

    $lowBook = Book::factory()->create(['is_active' => true]);
    $okBook = Book::factory()->create(['is_active' => true]);

    $lowInventory = Inventory::factory()->create([
        'book_id' => $lowBook->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 3,
        'reserved_quantity' => 1,
    ]);

    $okInventory = Inventory::factory()->create([
        'book_id' => $okBook->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 30,
        'reserved_quantity' => 0,
    ]);

    Livewire::actingAs($admin)
        ->test(ListInventories::class)
        ->set('activeTab', 'low')
        ->assertCanSeeTableRecords([$lowInventory])
        ->assertCanNotSeeTableRecords([$okInventory]);
});
