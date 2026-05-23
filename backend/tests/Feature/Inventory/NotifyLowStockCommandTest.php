<?php

use App\Enums\Account\AccountRole;
use App\Models\Account;
use App\Models\Book;
use App\Models\Inventory;
use App\Models\Warehouse;
use App\Notifications\Inventory\LowStockBooksNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['inventory.low_stock_threshold' => 5]);
    Cache::flush();
    Notification::fake();
});

function seedLowStockInventory(): Inventory
{
    $warehouse = Warehouse::factory()->create();
    $book = Book::factory()->create(['is_active' => true]);

    return Inventory::factory()->create([
        'book_id' => $book->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 4,
        'reserved_quantity' => 1,
    ]);
}

test('notify low stock command sends database notification to active admins only', function (): void {
    seedLowStockInventory();

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

    Artisan::call('inventory:notify-low-stock');

    Notification::assertSentTo(
        $activeAdmin,
        LowStockBooksNotification::class,
    );

    Notification::assertNotSentTo(
        Account::query()->where('role', AccountRole::Customer)->firstOrFail(),
        LowStockBooksNotification::class,
    );

    Notification::assertNotSentTo(
        Account::query()->where('role', AccountRole::Admin)->where('is_active', false)->firstOrFail(),
        LowStockBooksNotification::class,
    );
});

test('notify low stock command does not send duplicate alert on same day for unchanged set', function (): void {
    seedLowStockInventory();

    Account::factory()->create([
        'role' => AccountRole::Admin,
        'is_active' => true,
    ]);

    Artisan::call('inventory:notify-low-stock');
    Notification::assertSentTimes(LowStockBooksNotification::class, 1);

    Notification::fake();

    Artisan::call('inventory:notify-low-stock');
    Notification::assertNothingSent();
});

test('notify low stock command sends again when low stock set changes same day', function (): void {
    $first = seedLowStockInventory();

    Account::factory()->create([
        'role' => AccountRole::Admin,
        'is_active' => true,
    ]);

    Artisan::call('inventory:notify-low-stock');
    Notification::assertSentTimes(LowStockBooksNotification::class, 1);

    $warehouse = Warehouse::query()->firstOrFail();
    $anotherBook = Book::factory()->create(['is_active' => true]);
    Inventory::factory()->create([
        'book_id' => $anotherBook->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 2,
        'reserved_quantity' => 0,
    ]);

    Notification::fake();

    Artisan::call('inventory:notify-low-stock');
    Notification::assertSentTimes(LowStockBooksNotification::class, 1);

    expect($first->book_id)->not->toBe($anotherBook->id);
});

test('notify low stock command does nothing when no low stock inventories', function (): void {
    Account::factory()->create([
        'role' => AccountRole::Admin,
        'is_active' => true,
    ]);

    Artisan::call('inventory:notify-low-stock');

    Notification::assertNothingSent();
});
