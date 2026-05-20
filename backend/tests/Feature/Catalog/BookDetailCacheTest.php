<?php

use App\Models\Account;
use App\Models\Book;
use App\Models\Inventory;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\Warehouse;
use App\Services\Catalog\CatalogCacheService;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutMiddleware(VerifyCsrfToken::class);
    Cache::flush();
});

function bookDetailCacheFixture(): array
{
    $book = Book::factory()->create();
    Inventory::factory()->create([
        'book_id' => $book->id,
        'warehouse_id' => Warehouse::factory(),
        'quantity' => 5,
        'reserved_quantity' => 0,
    ]);

    $ship = ShippingMethod::query()->create([
        'name' => 'Standard',
        'description' => null,
        'is_active' => true,
    ]);

    ShippingRate::query()->create([
        'shipping_method_id' => $ship->id,
        'province_code' => '01',
        'base_fee' => 30000,
    ]);

    return ['book' => $book, 'shipping_method_id' => $ship->id];
}

test('checkout reserved_quantity change does not flush book detail cache', function (): void {
    ['book' => $book, 'shipping_method_id' => $shipId] = bookDetailCacheFixture();
    $catalogCache = app(CatalogCacheService::class);
    $detailKey = $catalogCache->bookDetailCacheKey($book->slug);
    $stockKey = $catalogCache->bookStockCacheKey($book->id);

    $this->getJson('/api/v1/books/'.$book->slug)->assertOk();

    expect(Cache::has($detailKey))->toBeTrue()
        ->and(Cache::has($stockKey))->toBeTrue();

    $account = Account::factory()->create();

    $this->actingAs($account)->postJson('/api/v1/cart/items', [
        'book_id' => $book->id,
        'quantity' => 1,
    ])->assertCreated();

    $this->actingAs($account)->postJson('/api/v1/checkout', [
        'idempotency_key' => (string) Str::uuid(),
        'payment_method' => 'cod',
        'shipping_method_id' => $shipId,
        'shipping' => [
            'recipient_name' => 'Nguyen Van A',
            'recipient_phone' => '0900000000',
            'province_code' => '01',
            'ward_code' => '00070',
            'detail_address' => '1 Test St',
        ],
    ])->assertCreated();

    expect(Cache::has($detailKey))->toBeTrue()
        ->and(Cache::has($stockKey))->toBeFalse();

    $this->getJson('/api/v1/books/'.$book->slug)
        ->assertOk()
        ->assertJsonPath('data.available_stock', 4)
        ->assertJsonPath('data.in_stock', true);
});

test('inventory save forgets book stock micro-cache', function (): void {
    ['book' => $book] = bookDetailCacheFixture();
    $catalogCache = app(CatalogCacheService::class);
    $stockKey = $catalogCache->bookStockCacheKey($book->id);

    Cache::put($stockKey, ['available_stock' => 5, 'in_stock' => true], 30);

    $inventory = Inventory::query()->where('book_id', $book->id)->firstOrFail();
    $inventory->increment('reserved_quantity');

    expect(Cache::has($stockKey))->toBeFalse();
});

test('book detail api reflects updated stock after inventory reservation', function (): void {
    ['book' => $book] = bookDetailCacheFixture();

    $this->getJson('/api/v1/books/'.$book->slug)
        ->assertOk()
        ->assertJsonPath('data.available_stock', 5);

    $inventory = Inventory::query()->where('book_id', $book->id)->firstOrFail();
    $inventory->update(['reserved_quantity' => 3]);

    $this->getJson('/api/v1/books/'.$book->slug)
        ->assertOk()
        ->assertJsonPath('data.available_stock', 2)
        ->assertJsonPath('data.in_stock', true);
});
