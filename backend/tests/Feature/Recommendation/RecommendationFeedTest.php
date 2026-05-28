<?php

use App\Enums\Order\OrderStatus;
use App\Jobs\Recommendation\BuildPopularRecommendations;
use App\Models\Account;
use App\Models\Book;
use App\Models\Inventory;
use App\Models\Warehouse;
use App\Services\Recommendation\RecommendationCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function createBookWithStock(int $stock, array $attributes = []): Book
{
    $book = Book::factory()->create($attributes);
    $warehouseId = (int) (Warehouse::query()->value('id') ?? Warehouse::factory()->create()->id);

    Inventory::factory()->create([
        'book_id' => $book->id,
        'warehouse_id' => $warehouseId,
        'quantity' => max($stock, 0),
        'reserved_quantity' => 0,
    ]);

    return $book;
}

function createCompletedOrderItem(Book $book, int $quantity): void
{
    $account = Account::factory()->create();
    $shippingMethodId = (int) DB::table('shipping_methods')->insertGetId([
        'name' => 'Nhanh',
        'description' => 'Giao nhanh',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $orderId = (int) DB::table('orders')->insertGetId([
        'account_id' => $account->id,
        'shipping_method_id' => $shippingMethodId,
        'total_amount' => 100000,
        'shipping_fee' => 0,
        'final_amount' => 100000,
        'shipping_name' => 'Test User',
        'shipping_phone' => '0900000000',
        'shipping_address' => 'HCM',
        'payment_method' => null,
        'payment_status' => null,
        'payment_expires_at' => null,
        'refund_deadline_at' => null,
        'note' => null,
        'current_status' => OrderStatus::COMPLETED->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('order_items')->insert([
        'order_id' => $orderId,
        'book_id' => $book->id,
        'promotion_item_id' => null,
        'promotion_id' => null,
        'price' => 100000,
        'quantity' => $quantity,
        'total_price' => 100000 * $quantity,
        'discount_amount' => null,
        'is_reviewed' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('recommendations endpoint returns popular feed and filters inactive and out of stock books', function (): void {
    $bookInStock = createBookWithStock(5, ['name' => 'Book A']);
    $bookOutOfStock = createBookWithStock(0, ['name' => 'Book B']);
    $bookInactive = createBookWithStock(3, ['is_active' => false, 'name' => 'Book C']);

    app(RecommendationCacheService::class)->putPopular([
        $bookOutOfStock->id,
        $bookInactive->id,
        $bookInStock->id,
    ]);

    $response = $this->getJson('/api/v1/recommendations?limit=10');

    $response->assertOk()
        ->assertJsonPath('meta.feed', 'for_you')
        ->assertJsonPath('meta.strategy', 'popular')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $bookInStock->id)
        ->assertJsonPath('data.0.available_stock', 5);
});

test('recommendations endpoint returns empty data when popular cache missing', function (): void {
    $response = $this->getJson('/api/v1/recommendations');

    $response->assertOk()
        ->assertJsonPath('meta.feed', 'for_you')
        ->assertJsonPath('meta.strategy', 'popular')
        ->assertJsonPath('data', []);
});

test('build popular recommendations job writes cache payload with strategy and candidate ids', function (): void {
    $bookHighSales = createBookWithStock(5);
    $bookNoSales = createBookWithStock(5);
    $bookInactive = createBookWithStock(5, ['is_active' => false]);

    createCompletedOrderItem($bookHighSales, 6);
    createCompletedOrderItem($bookNoSales, 1);
    createCompletedOrderItem($bookInactive, 20);

    $job = app(BuildPopularRecommendations::class);
    $job->handle(
        app(\App\Services\Recommendation\RecommendationCandidateService::class),
        app(RecommendationCacheService::class),
    );

    $payload = app(RecommendationCacheService::class)->getPopular();

    expect($payload)->not->toBeNull()
        ->and($payload['strategy'])->toBe('popular')
        ->and($payload['book_ids'])->toContain($bookHighSales->id)
        ->and($payload['book_ids'])->toContain($bookNoSales->id)
        ->and($payload['book_ids'])->not->toContain($bookInactive->id);
});
