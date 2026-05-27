<?php

use App\Models\Account;
use App\Models\Address;
use App\Models\Book;
use App\Models\CartItem;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\PromotionAllocation;
use App\Models\PromotionItem;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\Warehouse;
use App\Services\Payment\VnPayService;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutMiddleware(VerifyCsrfToken::class);
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
                'province' => [
                    'code' => $provinceCode,
                    'name' => $provinceCode === '01' ? 'Hà Nội' : 'Tỉnh test',
                    'type' => $provinceCode === '01' ? 'Thành phố' : 'Tỉnh',
                ],
                'ward' => [
                    'code' => $wardCode,
                    'name' => $wardCode === '00070' ? 'Hoàn Kiếm' : 'Phúc Xá',
                    'type' => 'Phường',
                    'province_code' => $provinceCode,
                ],
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

test('guest cannot checkout', function (): void {
    $this->postJson('/api/v1/checkout', [])->assertUnauthorized();
});

test('cod checkout with manual shipping creates order and clears selected cart lines', function (): void {
    $account = Account::factory()->create();
    $book = checkoutBookWithStock(5);
    $ship = checkoutShippingMethodForProvince('01');
    $idem = (string) Str::uuid();

    $this->actingAs($account)->postJson('/api/v1/cart/items', [
        'book_id' => $book->id,
        'quantity' => 2,
    ])->assertCreated();

    $response = $this->actingAs($account)->postJson('/api/v1/checkout', [
        'idempotency_key' => $idem,
        'payment_method' => 'cod',
        'shipping_method_id' => $ship->id,
        'shipping' => [
            'recipient_name' => 'Nguyen Van A',
            'recipient_phone' => '0900000000',
            'province_code' => '01',
            'ward_code' => '00070',
            'detail_address' => '1 Test St',
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.order.payment_method', 'cod')
        ->assertJsonPath('data.order.current_status', 'confirmed')
        ->assertJsonPath('data.payment', null);

    $orderId = (int) $response->json('data.order.id');
    expect(Order::query()->where('account_id', $account->id)->count())->toBe(1)
        ->and(CartItem::query()->count())->toBe(0);

    $inv = Inventory::query()->where('book_id', $book->id)->firstOrFail();
    expect((int) $inv->reserved_quantity)->toBe(2);

    $order = Order::query()->findOrFail($orderId);
    expect($order->checkout_idempotency_key)->toBe($idem)
        ->and($order->current_status)->toBe(\App\Enums\Order\OrderStatus::CONFIRMED)
        ->and($order->payment_status)->toBe(\App\Enums\Order\PaymentStatus::PENDING)
        ->and($order->shipping_address)->toBe('1 Test St, phường Hoàn Kiếm, Thành phố Hà Nội')
        ->and((float) $order->final_amount)->toBe(230000.0);
});

test('checkout with address_id snapshots address onto order', function (): void {
    $account = Account::factory()->create();
    $book = checkoutBookWithStock();
    $ship = checkoutShippingMethodForProvince('01');

    $address = Address::query()->create([
        'account_id' => $account->id,
        'recipient_name' => 'Booked Here',
        'recipient_phone' => '0911111111',
        'province_code' => '01',
        'district_code' => null,
        'ward_code' => '00070',
        'detail_address' => '99 Lane',
        'is_default' => true,
    ]);

    $this->actingAs($account)->postJson('/api/v1/cart/items', [
        'book_id' => $book->id,
        'quantity' => 1,
    ])->assertCreated();

    $this->actingAs($account)->postJson('/api/v1/checkout', [
        'idempotency_key' => (string) Str::uuid(),
        'payment_method' => 'cod',
        'shipping_method_id' => $ship->id,
        'address_id' => $address->id,
        'pricing_expectations' => checkoutPricingExpectationsForBook($book),
    ])->assertCreated()
        ->assertJsonPath('data.order.shipping_name', 'Booked Here')
        ->assertJsonPath('data.order.shipping_phone', '0911111111');

    $order = Order::query()->where('account_id', $account->id)->firstOrFail();
    expect($order->shipping_address)->toBe('99 Lane, phường Hoàn Kiếm, Thành phố Hà Nội');
});

test('idempotent checkout returns same order', function (): void {
    $account = Account::factory()->create();
    $book = checkoutBookWithStock();
    $ship = checkoutShippingMethodForProvince('01');
    $idem = (string) Str::uuid();

    $this->actingAs($account)->postJson('/api/v1/cart/items', [
        'book_id' => $book->id,
        'quantity' => 1,
    ])->assertCreated();

    $payload = [
        'idempotency_key' => $idem,
        'payment_method' => 'cod',
        'shipping_method_id' => $ship->id,
        'shipping' => [
            'recipient_name' => 'Nguyen Van A',
            'recipient_phone' => '0900000000',
            'province_code' => '01',
            'ward_code' => '00070',
            'detail_address' => '1 Test St',
        ],
        'pricing_expectations' => checkoutPricingExpectationsForBook($book),
    ];

    $first = $this->actingAs($account)->postJson('/api/v1/checkout', $payload)->assertCreated();
    $second = $this->actingAs($account)->postJson('/api/v1/checkout', $payload)->assertCreated();

    expect($first->json('data.order.id'))->toBe($second->json('data.order.id'))
        ->and(Order::query()->count())->toBe(1);
});

test('vnpay checkout returns payment url', function (): void {
    $account = Account::factory()->create();
    $book = checkoutBookWithStock();
    $ship = checkoutShippingMethodForProvince('01');

    $this->actingAs($account)->postJson('/api/v1/cart/items', [
        'book_id' => $book->id,
        'quantity' => 1,
    ])->assertCreated();

    $response = $this->actingAs($account)->postJson('/api/v1/checkout', [
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
        'pricing_expectations' => checkoutPricingExpectationsForBook($book),
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.order.payment_method', 'vnpay');

    expect($response->json('data.payment.payment_url'))->toStartWith('https://sandbox.vnpayment.vn/paymentv2/vpcpay.html');
});

test('vnpay checkout still creates order when payment url generation fails', function (): void {
    $this->mock(VnPayService::class, function ($mock): void {
        $mock->shouldReceive('createPaymentUrl')
            ->once()
            ->andThrow(new \InvalidArgumentException('Simulated VNPay URL failure'));
    });

    $account = Account::factory()->create();
    $book = checkoutBookWithStock();
    $ship = checkoutShippingMethodForProvince('01');

    $this->actingAs($account)->postJson('/api/v1/cart/items', [
        'book_id' => $book->id,
        'quantity' => 1,
    ])->assertCreated();

    $response = $this->actingAs($account)->postJson('/api/v1/checkout', [
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
        'pricing_expectations' => checkoutPricingExpectationsForBook($book),
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.order.payment_method', 'vnpay')
        ->assertJsonPath('data.payment', null);

    expect(Order::query()->where('account_id', $account->id)->count())->toBe(1);
});

test('checkout fails when no shipping rate for province', function (): void {
    $account = Account::factory()->create();
    $book = checkoutBookWithStock();
    $ship = checkoutShippingMethodForProvince('01');

    $this->actingAs($account)->postJson('/api/v1/cart/items', [
        'book_id' => $book->id,
        'quantity' => 1,
    ])->assertCreated();

    $this->actingAs($account)->postJson('/api/v1/checkout', [
        'idempotency_key' => (string) Str::uuid(),
        'payment_method' => 'cod',
        'shipping_method_id' => $ship->id,
        'shipping' => [
            'recipient_name' => 'Nguyen Van A',
            'recipient_phone' => '0900000000',
            'province_code' => '99',
            'ward_code' => '00001',
            'detail_address' => 'Far away',
        ],
        'pricing_expectations' => checkoutPricingExpectationsForBook($book),
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['shipping_method_id']);
});

test('checkout applies active percentage promotion and reserves promotion allocation', function (): void {
    $account = Account::factory()->create();
    $book = checkoutBookWithStock(5);
    $ship = checkoutShippingMethodForProvince('01');

    $promotion = Promotion::query()->create([
        'name' => 'Flash test',
        'type' => 'flash_sale',
        'start_at' => now()->subMinute(),
        'end_at' => now()->addHour(),
        'status' => 'active',
    ]);

    $promotionItem = PromotionItem::query()->create([
        'promotion_id' => $promotion->id,
        'book_id' => $book->id,
        'discount_value' => 10,
        'stock_limit' => 5,
        'max_quantity_per_user' => 2,
    ]);

    $this->actingAs($account)->postJson('/api/v1/cart/items', [
        'book_id' => $book->id,
        'quantity' => 2,
    ])->assertCreated();

    $response = $this->actingAs($account)->postJson('/api/v1/checkout', [
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
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.order.total_amount', 180000)
        ->assertJsonPath('data.order.final_amount', 210000)
        ->assertJsonPath('data.order.items.0.promotion_id', $promotion->id)
        ->assertJsonPath('data.order.items.0.promotion_item_id', $promotionItem->id)
        ->assertJsonPath('data.order.items.0.discount_amount', 20000);

    $promotionItem->refresh();
    expect((int) $promotionItem->sold_quantity)->toBe(2)
        ->and(PromotionAllocation::query()->where('promotion_item_id', $promotionItem->id)->count())->toBe(1);
});

test('checkout applies a started scheduled flash sale without waiting for status sync', function (): void {
    $account = Account::factory()->create();
    $book = checkoutBookWithStock(5);
    $ship = checkoutShippingMethodForProvince('01');

    $promotion = Promotion::query()->create([
        'name' => 'Just started flash',
        'type' => 'flash_sale',
        'start_at' => now()->subMinute(),
        'end_at' => now()->addHour(),
        'status' => 'scheduled',
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
    ])->assertCreated()
        ->assertJsonPath('data.order.total_amount', 90000)
        ->assertJsonPath('data.order.items.0.promotion_item_id', $promotionItem->id);

    expect((int) $promotionItem->fresh()->sold_quantity)->toBe(1);
});

test('checkout only requires pricing expectations for selected flash sale lines', function (): void {
    $account = Account::factory()->create();
    $flashBook = checkoutBookWithStock(5);
    $regularBook = Book::factory()->create();
    Inventory::factory()->create([
        'book_id' => $regularBook->id,
        'warehouse_id' => Warehouse::query()->firstOrFail()->id,
        'quantity' => 5,
        'reserved_quantity' => 0,
    ]);
    $ship = checkoutShippingMethodForProvince('01');

    $promotion = Promotion::query()->create([
        'name' => 'Mixed cart flash',
        'type' => 'flash_sale',
        'start_at' => now()->subMinute(),
        'end_at' => now()->addHour(),
        'status' => 'active',
    ]);

    $promotionItem = PromotionItem::query()->create([
        'promotion_id' => $promotion->id,
        'book_id' => $flashBook->id,
        'discount_value' => 10,
    ]);

    foreach ([$flashBook, $regularBook] as $book) {
        $this->actingAs($account)->postJson('/api/v1/cart/items', [
            'book_id' => $book->id,
            'quantity' => 1,
        ])->assertCreated();
    }

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
        'pricing_expectations' => checkoutPricingExpectationsForBook($flashBook, 1, $promotionItem),
    ])->assertCreated()
        ->assertJsonPath('data.order.total_amount', 190000);

    expect(Order::query()->firstOrFail()->items()->count())->toBe(2);
});

test('checkout rejects promotion quantity above customer limit', function (): void {
    $account = Account::factory()->create();
    $book = checkoutBookWithStock(5);
    $ship = checkoutShippingMethodForProvince('01');

    $promotion = Promotion::query()->create([
        'name' => 'Flash test',
        'type' => 'flash_sale',
        'start_at' => now()->subMinute(),
        'end_at' => now()->addHour(),
        'status' => 'active',
    ]);

    $promotionItem = PromotionItem::query()->create([
        'promotion_id' => $promotion->id,
        'book_id' => $book->id,
        'discount_value' => 10,
        'stock_limit' => 5,
        'max_quantity_per_user' => 1,
    ]);

    $this->actingAs($account)->postJson('/api/v1/cart/items', [
        'book_id' => $book->id,
        'quantity' => 2,
    ])->assertCreated();

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
        'pricing_expectations' => checkoutPricingExpectationsForBook($book, 2, $promotionItem),
    ])->assertStatus(422)
        ->assertJsonPath('code', 'PROMOTION_UNAVAILABLE');
});

test('checkout only applies flash sale from the single active campaign', function (): void {
    $account = Account::factory()->create();
    $book = checkoutBookWithStock(5);
    $ship = checkoutShippingMethodForProvince('01');

    $campaign = Promotion::query()->create([
        'name' => 'Only active flash',
        'type' => 'flash_sale',
        'start_at' => now()->subMinute(),
        'end_at' => now()->addHour(),
        'status' => 'active',
    ]);

    $item = PromotionItem::query()->create([
        'promotion_id' => $campaign->id,
        'book_id' => $book->id,
        'discount_value' => 25,
        'stock_limit' => 5,
    ]);

    $this->actingAs($account)->postJson('/api/v1/cart/items', [
        'book_id' => $book->id,
        'quantity' => 1,
    ])->assertCreated();

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
        'pricing_expectations' => checkoutPricingExpectationsForBook($book, 1, $item),
    ])->assertCreated()
        ->assertJsonPath('data.order.total_amount', 75000)
        ->assertJsonPath('data.order.items.0.promotion_id', $campaign->id)
        ->assertJsonPath('data.order.items.0.promotion_item_id', $item->id)
        ->assertJsonPath('data.order.items.0.discount_amount', 25000);
});

test('checkout returns promotion unavailable and keeps cart when flash sale pricing expectation is stale', function (): void {
    $account = Account::factory()->create();
    $book = checkoutBookWithStock(5);
    $ship = checkoutShippingMethodForProvince('01');

    $promotion = Promotion::query()->create([
        'name' => 'Stale flash',
        'type' => 'flash_sale',
        'start_at' => now()->subMinute(),
        'end_at' => now()->addHour(),
        'status' => 'active',
    ]);

    $promotionItem = PromotionItem::query()->create([
        'promotion_id' => $promotion->id,
        'book_id' => $book->id,
        'discount_value' => 10,
    ]);

    $this->actingAs($account)->postJson('/api/v1/cart/items', [
        'book_id' => $book->id,
        'quantity' => 1,
    ])->assertCreated();

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
        'pricing_expectations' => [[
            'book_id' => $book->id,
            'promotion_item_id' => $promotionItem->id,
            'effective_unit_price' => 1,
            'line_total' => 1,
        ]],
    ])->assertStatus(422)
        ->assertJsonPath('code', 'PROMOTION_UNAVAILABLE');

    expect(Order::query()->count())->toBe(0)
        ->and(CartItem::query()->where('book_id', $book->id)->exists())->toBeTrue();
});

test('stale flash sale in a mixed cart preserves every selected line and cart refresh returns new quote', function (): void {
    $account = Account::factory()->create();
    $flashBook = checkoutBookWithStock(5);
    $regularBookA = Book::factory()->create();
    $regularBookB = Book::factory()->create();
    $warehouseId = Warehouse::query()->firstOrFail()->id;

    foreach ([$regularBookA, $regularBookB] as $regularBook) {
        Inventory::factory()->create([
            'book_id' => $regularBook->id,
            'warehouse_id' => $warehouseId,
            'quantity' => 5,
            'reserved_quantity' => 0,
        ]);
    }

    $ship = checkoutShippingMethodForProvince('01');
    $promotion = Promotion::query()->create([
        'name' => 'Mixed cart stale flash',
        'type' => 'flash_sale',
        'start_at' => now()->subMinute(),
        'end_at' => now()->addHour(),
        'status' => 'active',
    ]);

    $promotionItem = PromotionItem::query()->create([
        'promotion_id' => $promotion->id,
        'book_id' => $flashBook->id,
        'discount_value' => 10,
    ]);

    foreach ([$flashBook, $regularBookA, $regularBookB] as $book) {
        $this->actingAs($account)->postJson('/api/v1/cart/items', [
            'book_id' => $book->id,
            'quantity' => 1,
        ])->assertCreated();
    }

    $staleExpectations = checkoutPricingExpectationsForBook($flashBook, 1, $promotionItem);
    $promotionItem->update(['discount_value' => 20]);

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
        'pricing_expectations' => $staleExpectations,
    ])->assertStatus(422)
        ->assertJsonPath('code', 'PROMOTION_UNAVAILABLE');

    expect(Order::query()->count())->toBe(0)
        ->and(CartItem::query()->where('selected', true)->count())->toBe(3);

    $this->actingAs($account)->getJson('/api/v1/cart')
        ->assertOk()
        ->assertJsonCount(3, 'data.items')
        ->assertJsonPath('data.items.0.book.id', $flashBook->id)
        ->assertJsonPath('data.items.0.effective_unit_price', 80000)
        ->assertJsonPath('data.items.0.promotion.discount_percent', 20);
});

test('checkout requires a refreshed quote when a flash sale starts after cart display', function (): void {
    $account = Account::factory()->create();
    $book = checkoutBookWithStock(5);
    $ship = checkoutShippingMethodForProvince('01');

    $this->actingAs($account)->postJson('/api/v1/cart/items', [
        'book_id' => $book->id,
        'quantity' => 1,
    ])->assertCreated();

    Promotion::query()->create([
        'name' => 'New flash',
        'type' => 'flash_sale',
        'start_at' => now()->subMinute(),
        'end_at' => now()->addHour(),
        'status' => 'active',
    ])->items()->create([
        'book_id' => $book->id,
        'discount_value' => 10,
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
    ])->assertStatus(422)
        ->assertJsonPath('code', 'PROMOTION_UNAVAILABLE');

    expect(Order::query()->count())->toBe(0)
        ->and(CartItem::query()->where('book_id', $book->id)->exists())->toBeTrue();
});

test('checkout rejects stale line_total when effective unit price rounds identically', function (): void {
    $account = Account::factory()->create();
    $book = checkoutBookWithStock(10);
    $book->update(['selling_price' => '10.01']);
    $book->refresh();
    $ship = checkoutShippingMethodForProvince('01');

    $promotion = Promotion::query()->create([
        'name' => 'Rounding flash',
        'type' => 'flash_sale',
        'start_at' => now()->subMinute(),
        'end_at' => now()->addHour(),
        'status' => 'active',
    ]);

    $promotionItem = PromotionItem::query()->create([
        'promotion_id' => $promotion->id,
        'book_id' => $book->id,
        'discount_value' => 50,
        'stock_limit' => 10,
    ]);

    $quote = app(\App\Services\Promotion\PromotionQuoteService::class)->quoteLine($book, 3, $promotionItem);

    expect($quote['effective_unit_price'])->toBe(5.0)
        ->and((float) $quote['line_total'])->toBe(15.02);

    $this->actingAs($account)->postJson('/api/v1/cart/items', [
        'book_id' => $book->id,
        'quantity' => 3,
    ])->assertCreated();

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
        'pricing_expectations' => [[
            'book_id' => $book->id,
            'promotion_item_id' => $promotionItem->id,
            'effective_unit_price' => $quote['effective_unit_price'],
            'line_total' => 15.00,
        ]],
    ])->assertStatus(422)
        ->assertJsonPath('code', 'PROMOTION_UNAVAILABLE');

    expect(Order::query()->count())->toBe(0)
        ->and(CartItem::query()->where('book_id', $book->id)->exists())->toBeTrue();
});

test('checkout fails when insufficient stock at commit time', function (): void {
    $account = Account::factory()->create();
    $book = checkoutBookWithStock(2);
    $ship = checkoutShippingMethodForProvince('01');

    $this->actingAs($account)->postJson('/api/v1/cart/items', [
        'book_id' => $book->id,
        'quantity' => 2,
    ])->assertCreated();

    Inventory::query()->where('book_id', $book->id)->update(['quantity' => 1]);

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
        'pricing_expectations' => checkoutPricingExpectationsForBook($book, 2),
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['cart']);

    expect(Order::query()->count())->toBe(0);
});

test('checkout fails when member cart has no selected lines', function (): void {
    $account = Account::factory()->create();
    $book = checkoutBookWithStock(5);
    $ship = checkoutShippingMethodForProvince('01');

    $add = $this->actingAs($account)->postJson('/api/v1/cart/items', [
        'book_id' => $book->id,
        'quantity' => 1,
    ])->assertCreated();

    $cartItemId = $add->json('data.cart_item_id');

    $this->actingAs($account)->patchJson("/api/v1/cart/items/{$cartItemId}", [
        'selected' => false,
    ])->assertOk();

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
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['cart']);

    expect(Order::query()->count())->toBe(0)
        ->and(CartItem::query()->whereKey($cartItemId)->exists())->toBeTrue();
});

test('checkout fails when member cart is empty', function (): void {
    $account = Account::factory()->create();
    $ship = checkoutShippingMethodForProvince('01');

    $this->actingAs($account)->getJson('/api/v1/cart')->assertOk();

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
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['cart']);

    expect(Order::query()->count())->toBe(0);
});

test('checkout removes only selected cart lines and keeps unselected lines', function (): void {
    $account = Account::factory()->create();
    $warehouseId = (int) (Warehouse::query()->value('id') ?? Warehouse::factory()->create()->id);
    $selectedBook = Book::factory()->create();
    $unselectedBook = Book::factory()->create();

    foreach ([$selectedBook, $unselectedBook] as $book) {
        Inventory::factory()->create([
            'book_id' => $book->id,
            'warehouse_id' => $warehouseId,
            'quantity' => 5,
            'reserved_quantity' => 0,
        ]);
    }

    $ship = checkoutShippingMethodForProvince('01');

    $this->actingAs($account)->postJson('/api/v1/cart/items', [
        'book_id' => $selectedBook->id,
        'quantity' => 1,
    ])->assertCreated();

    $unselectedAdd = $this->actingAs($account)->postJson('/api/v1/cart/items', [
        'book_id' => $unselectedBook->id,
        'quantity' => 2,
    ])->assertCreated();

    $unselectedItemId = $unselectedAdd->json('data.cart_item_id');

    $this->actingAs($account)->patchJson("/api/v1/cart/items/{$unselectedItemId}", [
        'selected' => false,
    ])->assertOk();

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
        'pricing_expectations' => checkoutPricingExpectationsForBook($selectedBook),
    ])->assertCreated();

    expect(Order::query()->count())->toBe(1)
        ->and(CartItem::query()->count())->toBe(1)
        ->and(CartItem::query()->where('book_id', $unselectedBook->id)->value('quantity'))->toBe(2);
});
