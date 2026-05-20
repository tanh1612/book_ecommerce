<?php

use App\Models\Account;
use App\Models\Address;
use App\Models\Book;
use App\Models\CartItem;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\Warehouse;
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

function checkoutBookWithStock(int $available = 10): Book
{
    $book = Book::factory()->create();
    Inventory::factory()->create([
        'book_id' => $book->id,
        'warehouse_id' => Warehouse::factory(),
        'quantity' => $available,
        'reserved_quantity' => 0,
    ]);

    return $book;
}

function checkoutShippingMethodForProvince(string $provinceCode = '01'): ShippingMethod
{
    $method = ShippingMethod::query()->create([
        'name' => 'Standard',
        'description' => null,
        'is_active' => true,
    ]);

    ShippingRate::query()->create([
        'shipping_method_id' => $method->id,
        'province_code' => $provinceCode,
        'base_fee' => 30000,
    ]);

    return $method;
}

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
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.order.payment_method', 'vnpay');

    expect($response->json('data.payment.payment_url'))->toStartWith('https://sandbox.vnpayment.vn/paymentv2/vpcpay.html');
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
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['shipping_method_id']);
});
