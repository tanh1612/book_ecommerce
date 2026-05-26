<?php

use App\Enums\Account\AccountRole;
use App\Enums\Inventory\InventoryStockAlertType;
use App\Jobs\Inventory\NotifyInventoryStockStatusChangedJob;
use App\Models\Account;
use App\Models\Book;
use App\Models\Inventory;
use App\Models\Warehouse;
use App\Notifications\Inventory\InventoryStockStatusNotification;
use App\Services\Inventory\InventoryStockStatusNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'inventory.low_stock_threshold' => 5,
        'inventory.low_stock_immediate_notifications' => true,
    ]);
    Notification::fake();
});

function updateInventoryInCommittedTransaction(Inventory $inventory, array $attributes): void
{
    DB::transaction(function () use ($inventory, $attributes): void {
        $inventory->update($attributes);
    });
}

function seedInventoryWithStock(int $quantity, int $reserved = 0): Inventory
{
    $warehouse = Warehouse::factory()->create();
    $book = Book::factory()->create(['is_active' => true]);

    return Inventory::factory()->create([
        'book_id' => $book->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => $quantity,
        'reserved_quantity' => $reserved,
        'location_code' => 'A-01',
    ]);
}

test('immediate stock alerts are skipped when config is disabled', function (): void {
    config(['inventory.low_stock_immediate_notifications' => false]);

    Bus::fake();

    $inventory = seedInventoryWithStock(20);
    updateInventoryInCommittedTransaction($inventory, ['quantity' => 3, 'reserved_quantity' => 0]);

    Bus::assertNotDispatched(NotifyInventoryStockStatusChangedJob::class);
    Notification::assertNothingSent();
});

test('low stock alert is sent when available stock crosses into low range', function (): void {
    Bus::fake();

    $inventory = seedInventoryWithStock(20);
    updateInventoryInCommittedTransaction($inventory, ['quantity' => 5, 'reserved_quantity' => 0]);

    Bus::assertDispatched(NotifyInventoryStockStatusChangedJob::class, function (NotifyInventoryStockStatusChangedJob $job) use ($inventory): bool {
        return $job->inventoryId === $inventory->id
            && $job->alertType === InventoryStockAlertType::LowStock;
    });
});

test('low stock alert is not sent again while stock remains in low range', function (): void {
    Bus::fake();

    $inventory = seedInventoryWithStock(6, 1);
    updateInventoryInCommittedTransaction($inventory, ['quantity' => 5, 'reserved_quantity' => 1]);

    Bus::assertDispatched(NotifyInventoryStockStatusChangedJob::class, 1);

    Bus::fake();

    updateInventoryInCommittedTransaction($inventory, ['quantity' => 4, 'reserved_quantity' => 1]);

    Bus::assertNotDispatched(NotifyInventoryStockStatusChangedJob::class);
});

test('out of stock alert is sent when available stock drops to zero', function (): void {
    Bus::fake();

    $inventory = seedInventoryWithStock(4);
    updateInventoryInCommittedTransaction($inventory, ['quantity' => 0, 'reserved_quantity' => 0]);

    Bus::assertDispatched(NotifyInventoryStockStatusChangedJob::class, function (NotifyInventoryStockStatusChangedJob $job) use ($inventory): bool {
        return $job->inventoryId === $inventory->id
            && $job->alertType === InventoryStockAlertType::OutOfStock;
    });
});

test('out of stock alert takes priority over low stock when stock drops to zero from above threshold', function (): void {
    Bus::fake();

    $inventory = seedInventoryWithStock(10);
    updateInventoryInCommittedTransaction($inventory, ['quantity' => 0, 'reserved_quantity' => 0]);

    Bus::assertDispatched(NotifyInventoryStockStatusChangedJob::class, 1);
    Bus::assertDispatched(NotifyInventoryStockStatusChangedJob::class, function (NotifyInventoryStockStatusChangedJob $job): bool {
        return $job->alertType === InventoryStockAlertType::OutOfStock;
    });
});

test('notifier sends only to active admins and skips inactive book for low stock', function (): void {
    Bus::fake();

    $activeAdmin = Account::factory()->create([
        'role' => AccountRole::Admin,
        'is_active' => true,
    ]);
    Account::factory()->create([
        'role' => AccountRole::Admin,
        'is_active' => false,
    ]);
    Account::factory()->create([
        'role' => AccountRole::Customer,
        'is_active' => true,
    ]);

    $inventory = seedInventoryWithStock(20);
    $inventory->book?->update(['is_active' => false]);

    Notification::fake();

    app(InventoryStockStatusNotifier::class)->notify($inventory->fresh(['book', 'warehouse']), InventoryStockAlertType::LowStock);

    Notification::assertNothingSent();

    $inventory->book?->update(['is_active' => true]);

    app(InventoryStockStatusNotifier::class)->notify($inventory->fresh(['book', 'warehouse']), InventoryStockAlertType::LowStock);

    Notification::assertSentTo($activeAdmin, InventoryStockStatusNotification::class);
    Notification::assertCount(1);
});

test('notifier still sends out of stock alert when book is inactive', function (): void {
    $activeAdmin = Account::factory()->create([
        'role' => AccountRole::Admin,
        'is_active' => true,
    ]);

    $inventory = seedInventoryWithStock(0);
    $inventory->book?->update(['is_active' => false]);

    app(InventoryStockStatusNotifier::class)->notify($inventory->fresh(['book', 'warehouse']), InventoryStockAlertType::OutOfStock);

    Notification::assertSentTo($activeAdmin, InventoryStockStatusNotification::class);
});

test('stock status job exits safely when inventory was deleted', function (): void {
    $inventory = seedInventoryWithStock(3);
    $inventoryId = $inventory->id;
    $inventory->delete();

    (new NotifyInventoryStockStatusChangedJob($inventoryId, InventoryStockAlertType::LowStock))
        ->handle(app(InventoryStockStatusNotifier::class));

    Notification::assertNothingSent();
});

test('creating inventory with low available stock dispatches low stock alert', function (): void {
    Bus::fake();

    $warehouse = Warehouse::factory()->create();
    $book = Book::factory()->create(['is_active' => true]);

    DB::transaction(function () use ($warehouse, $book): void {
        Inventory::factory()->create([
            'book_id' => $book->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 3,
            'reserved_quantity' => 0,
        ]);
    });

    Bus::assertDispatched(NotifyInventoryStockStatusChangedJob::class, function (NotifyInventoryStockStatusChangedJob $job): bool {
        return $job->alertType === InventoryStockAlertType::LowStock;
    });
});

test('restocking from out of stock to low range dispatches low stock alert again', function (): void {
    Bus::fake();

    $inventory = seedInventoryWithStock(0);
    updateInventoryInCommittedTransaction($inventory, ['quantity' => 3, 'reserved_quantity' => 0]);

    Bus::assertDispatched(NotifyInventoryStockStatusChangedJob::class, function (NotifyInventoryStockStatusChangedJob $job): bool {
        return $job->alertType === InventoryStockAlertType::LowStock;
    });
});
