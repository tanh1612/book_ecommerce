<?php

use App\Enums\Account\AccountRole;
use App\Filament\Resources\BookResource\Pages\EditBook;
use App\Filament\Resources\BookResource\RelationManagers\InventoriesRelationManager;
use App\Filament\Resources\InventoryResource\Pages\CreateInventory;
use App\Filament\Resources\WarehouseResource\RelationManagers\InventoriesRelationManager as WarehouseInventoriesRelationManager;
use App\Models\Account;
use App\Models\Book;
use App\Models\Inventory;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryRestockService;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutMiddleware(VerifyCsrfToken::class);
});

function restockBookAndWarehouse(): array
{
    $book = Book::factory()->create();
    $warehouse = Warehouse::query()->first() ?? Warehouse::factory()->create();

    return [$book, $warehouse];
}

test('creates inventory when book has no stock row', function (): void {
    [$book, $warehouse] = restockBookAndWarehouse();
    $restockedAt = now()->subDay();

    $result = app(InventoryRestockService::class)->createOrRestock([
        'book_id' => $book->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 25,
        'location_code' => 'A-01',
        'last_restocked_at' => $restockedAt,
    ]);

    expect($result->restocked)->toBeFalse()
        ->and($result->inventory->quantity)->toBe(25)
        ->and($result->inventory->location_code)->toBe('A-01')
        ->and(Inventory::query()->where('book_id', $book->id)->count())->toBe(1);
});

test('restocks existing inventory by adding quantity and updating metadata', function (): void {
    [$book, $warehouse] = restockBookAndWarehouse();

    $existing = Inventory::factory()->create([
        'book_id' => $book->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 40,
        'sold_quantity' => 5,
        'reserved_quantity' => 3,
        'location_code' => 'OLD',
        'last_restocked_at' => now()->subDays(5),
    ]);

    $newRestockedAt = now()->subDays(2);

    $result = app(InventoryRestockService::class)->createOrRestock([
        'book_id' => $book->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
        'location_code' => 'NEW-01',
        'last_restocked_at' => $newRestockedAt,
    ]);

    $existing->refresh();

    expect($result->restocked)->toBeTrue()
        ->and(Inventory::query()->where('book_id', $book->id)->count())->toBe(1)
        ->and($existing->quantity)->toBe(50)
        ->and($existing->sold_quantity)->toBe(5)
        ->and($existing->reserved_quantity)->toBe(3)
        ->and($existing->warehouse_id)->toBe($warehouse->id)
        ->and($existing->location_code)->toBe('NEW-01')
        ->and($existing->last_restocked_at?->format('Y-m-d H:i'))->toBe($newRestockedAt->format('Y-m-d H:i'));
});

test('rejects restock when last restocked at is before previous milestone', function (): void {
    [$book, $warehouse] = restockBookAndWarehouse();

    Inventory::factory()->create([
        'book_id' => $book->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
        'last_restocked_at' => now()->subDays(3),
    ]);

    app(InventoryRestockService::class)->createOrRestock([
        'book_id' => $book->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 5,
        'location_code' => 'B-01',
        'last_restocked_at' => now()->subDays(5),
    ]);
})->throws(ValidationException::class);

test('rejects restock when last restocked at is in the future', function (): void {
    [$book, $warehouse] = restockBookAndWarehouse();

    Inventory::factory()->create([
        'book_id' => $book->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
        'last_restocked_at' => now()->subDay(),
    ]);

    app(InventoryRestockService::class)->createOrRestock([
        'book_id' => $book->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 5,
        'location_code' => 'B-01',
        'last_restocked_at' => now()->addHour(),
    ]);
})->throws(ValidationException::class);

test('allows restock when previous last restocked at is null and only blocks future date', function (): void {
    [$book, $warehouse] = restockBookAndWarehouse();

    Inventory::factory()->create([
        'book_id' => $book->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
        'last_restocked_at' => null,
    ]);

    $newRestockedAt = now()->subHour();

    $result = app(InventoryRestockService::class)->createOrRestock([
        'book_id' => $book->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 4,
        'location_code' => 'C-02',
        'last_restocked_at' => $newRestockedAt,
    ]);

    expect($result->restocked)->toBeTrue()
        ->and($result->inventory->quantity)->toBe(14);
});

test('filament create inventory shows notification when restock date violates grace rule', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);
    [$book, $warehouse] = restockBookAndWarehouse();

    Inventory::factory()->create([
        'book_id' => $book->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
        'last_restocked_at' => now()->subDays(3),
    ]);

    Livewire::actingAs($admin)
        ->test(CreateInventory::class)
        ->fillForm([
            'book_id' => $book->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 5,
            'location_code' => 'X-01',
            'last_restocked_at' => now()->subDays(5)->format('Y-m-d H:i:s'),
        ])
        ->call('create')
        ->assertNotified()
        ->assertHasFormErrors(['last_restocked_at']);

    expect(Inventory::query()->where('book_id', $book->id)->firstOrFail()->quantity)->toBe(10);
});

test('filament create inventory restocks duplicate book instead of unique violation', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);
    [$book, $warehouse] = restockBookAndWarehouse();

    Inventory::factory()->create([
        'book_id' => $book->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 20,
        'last_restocked_at' => now()->subDays(4),
    ]);

    Livewire::actingAs($admin)
        ->test(CreateInventory::class)
        ->fillForm([
            'book_id' => $book->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 15,
            'location_code' => 'D-10',
            'last_restocked_at' => now()->subDay()->format('Y-m-d H:i:s'),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $inventory = Inventory::query()->where('book_id', $book->id)->firstOrFail();

    expect(Inventory::query()->where('book_id', $book->id)->count())->toBe(1)
        ->and($inventory->quantity)->toBe(35)
        ->and($inventory->location_code)->toBe('D-10');
});

test('book relation manager create action restocks existing inventory', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);
    [$book, $warehouse] = restockBookAndWarehouse();

    Inventory::factory()->create([
        'book_id' => $book->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 12,
        'last_restocked_at' => now()->subDays(2),
    ]);

    Livewire::actingAs($admin)
        ->test(InventoriesRelationManager::class, [
            'ownerRecord' => $book,
            'pageClass' => EditBook::class,
        ])
        ->callTableAction('create', data: [
            'warehouse_id' => $warehouse->id,
            'quantity' => 8,
            'location_code' => 'E-03',
            'last_restocked_at' => now()->subDay()->format('Y-m-d H:i:s'),
        ])
        ->assertHasNoActionErrors();

    $inventory = Inventory::query()->where('book_id', $book->id)->firstOrFail();

    expect($inventory->quantity)->toBe(20);
});

test('warehouse relation manager create action restocks existing inventory for same book', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);
    [$book, $warehouse] = restockBookAndWarehouse();

    Inventory::factory()->create([
        'book_id' => $book->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 7,
        'last_restocked_at' => now()->subDays(2),
    ]);

    Livewire::actingAs($admin)
        ->test(WarehouseInventoriesRelationManager::class, [
            'ownerRecord' => $warehouse,
            'pageClass' => \App\Filament\Resources\WarehouseResource\Pages\EditWarehouse::class,
        ])
        ->callTableAction('create', data: [
            'book_id' => $book->id,
            'quantity' => 3,
            'location_code' => 'F-04',
            'last_restocked_at' => now()->subDay()->format('Y-m-d H:i:s'),
        ])
        ->assertHasNoActionErrors();

    expect(Inventory::query()->where('book_id', $book->id)->firstOrFail()->quantity)->toBe(10);
});
