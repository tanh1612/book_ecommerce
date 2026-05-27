<?php

use App\Enums\Promotion\PromotionStatus;
use App\Models\Account;
use App\Models\Book;
use App\Models\Inventory;
use App\Models\Promotion;
use App\Models\PromotionItem;
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
        'pricing_expectations' => checkoutPricingExpectationsForBook($book),
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

test('promotion item update invalidates cached book detail flash sale payload', function (): void {
    $book = Book::factory()->create(['slug' => 'flash-cache-invalidate']);
    Inventory::factory()->create([
        'book_id' => $book->id,
        'warehouse_id' => Warehouse::factory(),
        'quantity' => 10,
        'reserved_quantity' => 0,
    ]);

    $promotion = Promotion::query()->create([
        'name' => 'Cache bust',
        'type' => 'flash_sale',
        'start_at' => now()->subMinute(),
        'end_at' => now()->addHour(),
        'status' => PromotionStatus::ACTIVE,
    ]);

    $item = PromotionItem::query()->create([
        'promotion_id' => $promotion->id,
        'book_id' => $book->id,
        'discount_value' => 10,
    ]);

    $detailKey = app(CatalogCacheService::class)->bookDetailCacheKey($book->slug);

    $this->getJson('/api/v1/books/'.$book->slug)
        ->assertOk()
        ->assertJsonPath('data.flash_sale.discount_percent', 10);

    expect(Cache::has($detailKey))->toBeTrue();

    $item->update(['discount_value' => 30]);

    expect(Cache::has($detailKey))->toBeFalse();

    $this->getJson('/api/v1/books/'.$book->slug)
        ->assertOk()
        ->assertJsonPath('data.flash_sale.discount_percent', 30);
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
