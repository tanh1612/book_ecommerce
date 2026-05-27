<?php

use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentMethod;
use App\Enums\Order\PaymentStatus;
use App\Enums\Promotion\PromotionAllocationStatus;
use App\Enums\Promotion\PromotionStatus;
use App\Models\Account;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\PromotionAllocation;
use App\Models\PromotionItem;
use App\Services\Order\OrderStatusTransitionService;
use App\Services\Payment\VnPayService;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutMiddleware(VerifyCsrfToken::class);
    \Illuminate\Support\Facades\Http::fake(function (\Illuminate\Http\Client\Request $request) {
        if (! str_contains($request->url(), 'new-full-address')) {
            return \Illuminate\Support\Facades\Http::response([], 404);
        }

        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $provinceCode = (string) ($query['provinceCode'] ?? '01');
        $wardCode = (string) ($query['wardCode'] ?? '00070');

        return \Illuminate\Support\Facades\Http::response([
            'success' => true,
            'data' => [
                'province' => ['code' => $provinceCode, 'name' => 'Hà Nội', 'type' => 'Thành phố'],
                'ward' => ['code' => $wardCode, 'name' => 'Hoàn Kiếm', 'type' => 'Phường', 'province_code' => $provinceCode],
            ],
        ], 200);
    });
    config([
        'vnpay.tmn_code' => 'TESTTMN01',
        'vnpay.hash_secret' => 'test-secret-key-32chars-minimum',
        'vnpay.payment_url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
        'vnpay.return_url' => 'https://example.test/api/v1/payments/vnpay/return',
        'vnpay.payment_ttl_hours' => 12,
        'vnpay.version' => '2.1.0',
        'vnpay.command' => 'pay',
        'vnpay.curr_code' => 'VND',
        'vnpay.locale' => 'vn',
        'app.timezone' => 'Asia/Ho_Chi_Minh',
    ]);
});

test('vnpay return confirms reserved flash sale allocation', function (): void {
    $account = Account::factory()->create();
    $book = checkoutBookWithStock(5);
    $ship = checkoutShippingMethodForProvince('01');

    $promotion = Promotion::query()->create([
        'name' => 'Pay confirm flash',
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

    $checkoutResponse = $this->actingAs($account)->postJson('/api/v1/checkout', [
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
        'pricing_expectations' => checkoutPricingExpectationsForBook($book, 1, $promotionItem),
    ])->assertCreated();

    $order = Order::query()->findOrFail((int) $checkoutResponse->json('data.order.id'));

    expect(PromotionAllocation::query()->where('promotion_item_id', $promotionItem->id)->first()?->status)
        ->toBe(PromotionAllocationStatus::RESERVED);

    $service = app(VnPayService::class);
    $service->createPaymentUrl($order, '127.0.0.1');

    $txn = $order->paymentTransactions()->firstOrFail();
    $parts = parse_url((string) $txn->payload['payment_url']);
    parse_str((string) ($parts['query'] ?? ''), $baseQuery);

    $query = array_merge($baseQuery, [
        'vnp_ResponseCode' => '00',
        'vnp_TransactionStatus' => '00',
        'vnp_TransactionNo' => '111222',
        'vnp_PayDate' => '20260518101530',
    ]);

    $secureHashMethod = new \ReflectionMethod(VnPayService::class, 'secureHash');
    $secureHashMethod->setAccessible(true);
    $query['vnp_SecureHash'] = $secureHashMethod->invoke($service, $query);

    $result = $service->handleReturn($query);

    expect($result['success'])->toBeTrue();

    $order->refresh();
    expect($order->payment_status)->toBe(PaymentStatus::PAID)
        ->and($order->current_status)->toBe(OrderStatus::CONFIRMED)
        ->and(PromotionAllocation::query()->where('promotion_item_id', $promotionItem->id)->first()?->status)
        ->toBe(PromotionAllocationStatus::CONFIRMED);
});

test('cod payment confirmation confirms reserved flash sale allocation', function (): void {
    $admin = Account::factory()->create();
    $customer = Account::factory()->create();
    $book = checkoutBookWithStock(5);
    $ship = checkoutShippingMethodForProvince('01');

    $promotion = Promotion::query()->create([
        'name' => 'COD confirm flash',
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
        'quantity' => 1,
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
        'pricing_expectations' => checkoutPricingExpectationsForBook($book, 1, $promotionItem),
    ])->assertCreated();

    $order = Order::query()->findOrFail((int) $checkoutResponse->json('data.order.id'));

    expect($order->payment_method)->toBe(PaymentMethod::COD)
        ->and(PromotionAllocation::query()->where('promotion_item_id', $promotionItem->id)->first()?->status)
        ->toBe(PromotionAllocationStatus::RESERVED);

    $order->update(['current_status' => OrderStatus::SHIPPING]);

    app(OrderStatusTransitionService::class)->confirmCodPayment($order->fresh(), $admin);

    expect(PromotionAllocation::query()->where('promotion_item_id', $promotionItem->id)->first()?->status)
        ->toBe(PromotionAllocationStatus::CONFIRMED);
});
