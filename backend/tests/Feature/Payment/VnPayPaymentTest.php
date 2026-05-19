<?php

use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentMethod;
use App\Enums\Order\PaymentStatus;
use App\Enums\Payment\PaymentGateway;
use App\Enums\Payment\PaymentTransactionStatus;
use App\Models\Account;
use App\Models\Book;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentTransaction;
use App\Models\ShippingMethod;
use App\Services\Payment\VnPayService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
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

function createVnPayOrder(): Order
{
    $account = Account::factory()->create();
    $shipping = ShippingMethod::query()->create([
        'name' => 'Test ship',
        'description' => null,
        'is_active' => true,
    ]);

    return Order::query()->create([
        'account_id' => $account->id,
        'shipping_method_id' => $shipping->id,
        'total_amount' => 100000.00,
        'shipping_fee' => 0,
        'final_amount' => 100000.00,
        'shipping_name' => 'Nguyen Van A',
        'shipping_phone' => '0900000000',
        'shipping_address' => '1 Test St',
        'payment_method' => PaymentMethod::VNPAY,
        'payment_status' => PaymentStatus::PENDING,
        'note' => null,
        'tracking_number' => null,
        'current_status' => OrderStatus::PENDING,
    ]);
}

test('creates vnpay payment url and pending transaction', function (): void {
    $order = createVnPayOrder();
    $service = app(VnPayService::class);

    $result = $service->createPaymentUrl($order, '127.0.0.1');

    expect($result['payment_url'])->toContain('https://sandbox.vnpayment.vn/paymentv2/vpcpay.html?')
        ->and($result['payment_url'])->toContain('vnp_SecureHash=')
        ->and($result['vnp_TxnRef'])->not->toBeEmpty();

    $order->refresh();
    expect($order->payment_expires_at)->not->toBeNull()
        ->and($order->payment_status)->toBe(PaymentStatus::PENDING);

    $txn = PaymentTransaction::query()->where('order_id', $order->id)->first();
    expect($txn)->not->toBeNull()
        ->and($txn->gateway)->toBe(PaymentGateway::VNPAY)
        ->and($txn->status)->toBe(PaymentTransactionStatus::PENDING)
        ->and($txn->gateway_txn_id)->toBe($result['vnp_TxnRef']);
});

test('vnpay return rejects invalid signature', function (): void {
    $order = createVnPayOrder();
    $service = app(VnPayService::class);
    $service->createPaymentUrl($order, '127.0.0.1');

    $txn = PaymentTransaction::query()->where('order_id', $order->id)->firstOrFail();

    $response = $this->getJson('/api/v1/payments/vnpay/return?'.http_build_query([
        'vnp_TxnRef' => $txn->gateway_txn_id,
        'vnp_Amount' => '10000000',
        'vnp_ResponseCode' => '00',
        'vnp_TransactionStatus' => '00',
        'vnp_SecureHash' => 'deadbeef',
    ]));

    $response->assertStatus(422)
        ->assertJsonFragment(['success' => false]);
});

test('vnpay return marks order paid on success', function (): void {
    $order = createVnPayOrder();
    $service = app(VnPayService::class);
    $service->createPaymentUrl($order, '127.0.0.1');

    $txn = PaymentTransaction::query()->where('order_id', $order->id)->firstOrFail();
    $order->refresh();

    $parts = parse_url((string) $txn->payload['payment_url']);
    parse_str((string) ($parts['query'] ?? ''), $baseQuery);

    $query = array_merge($baseQuery, [
        'vnp_ResponseCode' => '00',
        'vnp_TransactionStatus' => '00',
        'vnp_TransactionNo' => '999888',
        'vnp_PayDate' => '20260518101530',
    ]);

    $ref = new ReflectionMethod(VnPayService::class, 'secureHash');
    $ref->setAccessible(true);
    $query['vnp_SecureHash'] = $ref->invoke($service, $query);

    $result = $service->handleReturn($query);

    expect($result['success'])->toBeTrue()
        ->and($result['payment_status'])->toBe('paid');

    $order->refresh();
    expect($order->payment_status)->toBe(PaymentStatus::PAID)
        ->and($order->current_status)->toBe(OrderStatus::CONFIRMED);

    $txn->refresh();
    expect($txn->status)->toBe(PaymentTransactionStatus::PAID);
});

test('expire command cancels unpaid vnpay orders past expiry', function (): void {
    $order = createVnPayOrder();
    $service = app(VnPayService::class);
    $service->createPaymentUrl($order, '127.0.0.1');

    $order->refresh();
    $order->update(['payment_expires_at' => now()->subMinute()]);

    $this->artisan('payments:expire-vnpay')->assertSuccessful();

    $order->refresh();
    expect($order->current_status)->toBe(OrderStatus::CANCELLED)
        ->and($order->payment_status)->toBe(PaymentStatus::FAILED);

    $txn = PaymentTransaction::query()->where('order_id', $order->id)->firstOrFail();
    expect($txn->status)->toBe(PaymentTransactionStatus::EXPIRED);
});

test('expire command releases reserved inventory for order items', function (): void {
    $book = Book::factory()->create();
    Inventory::factory()->create([
        'book_id' => $book->id,
        'quantity' => 10,
        'reserved_quantity' => 3,
    ]);

    $order = createVnPayOrder();
    OrderItem::query()->create([
        'order_id' => $order->id,
        'book_id' => $book->id,
        'promotion_id' => null,
        'price' => 100000,
        'quantity' => 3,
        'total_price' => 300000,
        'discount_amount' => 0,
        'is_reviewed' => false,
    ]);

    $order->update(['payment_expires_at' => now()->subMinute()]);

    $this->artisan('payments:expire-vnpay')->assertSuccessful();

    $inventory = Inventory::query()->where('book_id', $book->id)->firstOrFail();
    expect((int) $inventory->reserved_quantity)->toBe(0);
});
