<?php

use App\Enums\Order\OrderStatus;
use App\Enums\Promotion\PromotionAllocationStatus;
use App\Enums\Promotion\PromotionStatus;
use App\Models\Account;
use App\Models\Book;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\PromotionAllocation;
use App\Models\PromotionItem;
use App\Models\Warehouse;
use App\Services\Order\OrderStatusTransitionService;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    test()->withoutMiddleware(VerifyCsrfToken::class);

    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        if (! str_contains($request->url(), 'new-full-address')) {
            return Http::response([], 404);
        }

        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $provinceCode = (string) ($query['provinceCode'] ?? '01');
        $wardCode = (string) ($query['wardCode'] ?? '00070');

        return Http::response([
            'success' => true,
            'data' => [
                'province' => ['code' => $provinceCode, 'name' => 'Hà Nội', 'type' => 'Thành phố'],
                'ward' => ['code' => $wardCode, 'name' => 'Hoàn Kiếm', 'type' => 'Phường', 'province_code' => $provinceCode],
            ],
        ], 200);
    });
});

test('checkout rejects flash sale when campaign already expired before pricing resolve', function (): void {
    $account = Account::factory()->create();
    $book = checkoutBookWithStock(5);
    $ship = checkoutShippingMethodForProvince('01');

    $promotion = Promotion::query()->create([
        'name' => 'Expiring flash',
        'type' => 'flash_sale',
        'start_at' => now()->subMinute(),
        'end_at' => now()->addHour(),
        'status' => PromotionStatus::ACTIVE,
    ]);

    $promotionItem = PromotionItem::query()->create([
        'promotion_id' => $promotion->id,
        'book_id' => $book->id,
        'discount_value' => 10,
        'stock_limit' => 5,
    ]);

    $this->actingAs($account)->postJson('/api/v1/cart/items', [
        'book_id' => $book->id,
        'quantity' => 1,
    ])->assertCreated();

    $promotion->update([
        'status' => PromotionStatus::EXPIRED,
        'end_at' => now()->subSecond(),
    ]);

    $this->actingAs($account)->postJson('/api/v1/checkout', [
        'idempotency_key' => (string) Str::uuid(),
        'payment_method' => 'cod',
        'shipping_method_id' => $ship->id,
        'shipping' => [
            'recipient_name' => 'Nguyen Van A',
            'recipient_phone' => '0900000000',
            'province_code' => '01',
            'ward_code' => '00070',
            'detail_address' => '1 Test St',
        ],
        'pricing_expectations' => checkoutPricingExpectationsForBook($book, 1, $promotionItem),
    ])->assertStatus(422)
        ->assertJsonPath('code', 'PROMOTION_UNAVAILABLE');

    expect(Order::query()->count())->toBe(0)
        ->and((int) $promotionItem->fresh()->sold_quantity)->toBe(0)
        ->and(PromotionAllocation::query()->count())->toBe(0);
});

test('sequential checkouts cannot oversell the last flash sale unit', function (): void {
    $book = checkoutBookWithStock(10);
    $ship = checkoutShippingMethodForProvince('01');
    $accountA = Account::factory()->create();
    $accountB = Account::factory()->create();

    $promotion = Promotion::query()->create([
        'name' => 'One left',
        'type' => 'flash_sale',
        'start_at' => now()->subMinute(),
        'end_at' => now()->addHour(),
        'status' => PromotionStatus::ACTIVE,
    ]);

    $promotionItem = PromotionItem::query()->create([
        'promotion_id' => $promotion->id,
        'book_id' => $book->id,
        'discount_value' => 10,
        'stock_limit' => 1,
    ]);

    foreach ([$accountA, $accountB] as $account) {
        $this->actingAs($account)->postJson('/api/v1/cart/items', [
            'book_id' => $book->id,
            'quantity' => 1,
        ])->assertCreated();
    }

    $this->actingAs($accountA)->postJson('/api/v1/checkout', [
        'idempotency_key' => (string) Str::uuid(),
        'payment_method' => 'cod',
        'shipping_method_id' => $ship->id,
        'shipping' => [
            'recipient_name' => 'Buyer A',
            'recipient_phone' => '0900000001',
            'province_code' => '01',
            'ward_code' => '00070',
            'detail_address' => '1 Test St',
        ],
        'pricing_expectations' => checkoutPricingExpectationsForBook($book, 1, $promotionItem),
    ])->assertCreated();

    $this->actingAs($accountB)->postJson('/api/v1/checkout', [
        'idempotency_key' => (string) Str::uuid(),
        'payment_method' => 'cod',
        'shipping_method_id' => $ship->id,
        'shipping' => [
            'recipient_name' => 'Buyer B',
            'recipient_phone' => '0900000002',
            'province_code' => '01',
            'ward_code' => '00070',
            'detail_address' => '2 Test St',
        ],
        'pricing_expectations' => checkoutPricingExpectationsForBook($book, 1, $promotionItem),
    ])->assertStatus(422)
        ->assertJsonPath('code', 'PROMOTION_UNAVAILABLE');

    expect(Order::query()->count())->toBe(1)
        ->and((int) $promotionItem->fresh()->sold_quantity)->toBe(1);
});

test('cancelling confirmed order releases flash sale allocation and sold quantity', function (): void {
    $admin = Account::factory()->create();
    $customer = Account::factory()->create();
    $book = checkoutBookWithStock(10);
    $ship = checkoutShippingMethodForProvince('01');

    $promotion = Promotion::query()->create([
        'name' => 'Releasable flash',
        'type' => 'flash_sale',
        'start_at' => now()->subMinute(),
        'end_at' => now()->addHour(),
        'status' => PromotionStatus::ACTIVE,
    ]);

    $promotionItem = PromotionItem::query()->create([
        'promotion_id' => $promotion->id,
        'book_id' => $book->id,
        'discount_value' => 10,
        'stock_limit' => 5,
    ]);

    $this->actingAs($customer)->postJson('/api/v1/cart/items', [
        'book_id' => $book->id,
        'quantity' => 2,
    ])->assertCreated();

    $checkoutResponse = $this->actingAs($customer)->postJson('/api/v1/checkout', [
        'idempotency_key' => (string) Str::uuid(),
        'payment_method' => 'cod',
        'shipping_method_id' => $ship->id,
        'shipping' => [
            'recipient_name' => 'Nguyen Van A',
            'recipient_phone' => '0900000000',
            'province_code' => '01',
            'ward_code' => '00070',
            'detail_address' => '1 Test St',
        ],
        'pricing_expectations' => checkoutPricingExpectationsForBook($book, 2, $promotionItem),
    ])->assertCreated();

    $orderId = (int) $checkoutResponse->json('data.order.id');
    $order = Order::query()->findOrFail($orderId);

    expect((int) $promotionItem->fresh()->sold_quantity)->toBe(2)
        ->and(PromotionAllocation::query()->where('promotion_item_id', $promotionItem->id)->first()?->status)
        ->toBe(PromotionAllocationStatus::RESERVED);

    app(OrderStatusTransitionService::class)->cancelConfirmedOrder($order->fresh(), $admin, 'Test cancel');

    expect((int) $promotionItem->fresh()->sold_quantity)->toBe(0)
        ->and(PromotionAllocation::query()->where('promotion_item_id', $promotionItem->id)->first()?->status)
        ->toBe(PromotionAllocationStatus::RELEASED);
});

test('expiring unpaid vnpay order releases flash sale allocation and sold quantity', function (): void {
    $customer = Account::factory()->create();
    $book = checkoutBookWithStock(10);
    $ship = checkoutShippingMethodForProvince('01');

    $promotion = Promotion::query()->create([
        'name' => 'VNPay expire flash',
        'type' => 'flash_sale',
        'start_at' => now()->subMinute(),
        'end_at' => now()->addHour(),
        'status' => PromotionStatus::ACTIVE,
    ]);

    $promotionItem = PromotionItem::query()->create([
        'promotion_id' => $promotion->id,
        'book_id' => $book->id,
        'discount_value' => 10,
        'stock_limit' => 5,
    ]);

    $this->actingAs($customer)->postJson('/api/v1/cart/items', [
        'book_id' => $book->id,
        'quantity' => 2,
    ])->assertCreated();

    $checkoutResponse = $this->actingAs($customer)->postJson('/api/v1/checkout', [
        'idempotency_key' => (string) Str::uuid(),
        'payment_method' => 'vnpay',
        'shipping_method_id' => $ship->id,
        'shipping' => [
            'recipient_name' => 'Nguyen Van A',
            'recipient_phone' => '0900000000',
            'province_code' => '01',
            'ward_code' => '00070',
            'detail_address' => '1 Test St',
        ],
        'pricing_expectations' => checkoutPricingExpectationsForBook($book, 2, $promotionItem),
    ])->assertCreated();

    $order = Order::query()->findOrFail((int) $checkoutResponse->json('data.order.id'));

    app(\App\Services\Payment\VnPayService::class)->createPaymentUrl($order, '127.0.0.1');

    expect((int) $promotionItem->fresh()->sold_quantity)->toBe(2)
        ->and(PromotionAllocation::query()->where('promotion_item_id', $promotionItem->id)->first()?->status)
        ->toBe(PromotionAllocationStatus::RESERVED);

    $order->update(['payment_expires_at' => now()->subMinute()]);

    $this->artisan('payments:expire-vnpay')->assertSuccessful();

    $order->refresh();

    expect($order->current_status)->toBe(\App\Enums\Order\OrderStatus::CANCELLED)
        ->and((int) $promotionItem->fresh()->sold_quantity)->toBe(0)
        ->and(PromotionAllocation::query()->where('promotion_item_id', $promotionItem->id)->first()?->status)
        ->toBe(PromotionAllocationStatus::RELEASED);
});
