<?php

use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentMethod;
use App\Enums\Order\PaymentStatus;
use App\Enums\Payment\PaymentGateway;
use App\Enums\Payment\PaymentTransactionStatus;
use App\Enums\Payment\PaymentTransactionType;
use App\Models\Account;
use App\Models\Order;
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
        'vnpay.ipn_url' => 'https://example.test/api/v1/payments/vnpay/ipn',
        'vnpay.payment_ttl_hours' => 12,
        'vnpay.version' => '2.1.0',
        'vnpay.command' => 'pay',
        'vnpay.curr_code' => 'VND',
        'vnpay.locale' => 'vn',
        'app.timezone' => 'Asia/Ho_Chi_Minh',
    ]);
});

function vnpayRetryOrder(Account $account, ?\Illuminate\Support\Carbon $paymentExpiresAt = null): Order
{
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
        'current_status' => OrderStatus::PENDING,
        'payment_expires_at' => $paymentExpiresAt ?? now()->addHours(12),
    ]);
}

function vnpaySignedSuccessReturn(VnPayService $service, PaymentTransaction $txn, Order $order): array
{
    $parts = parse_url((string) ($txn->payload['payment_url'] ?? ''));
    parse_str((string) ($parts['query'] ?? ''), $baseQuery);

    $query = array_merge($baseQuery, [
        'vnp_TxnRef' => $txn->gateway_txn_id,
        'vnp_Amount' => (string) (int) round((float) $order->final_amount * 100),
        'vnp_ResponseCode' => '00',
        'vnp_TransactionStatus' => '00',
        'vnp_TransactionNo' => '999888',
        'vnp_PayDate' => '20260518101530',
    ]);

    $ref = new ReflectionMethod(VnPayService::class, 'secureHash');
    $ref->setAccessible(true);
    $query['vnp_SecureHash'] = $ref->invoke($service, $query);

    return $query;
}

test('account order detail exposes can_pay for unpaid vnpay pending order', function (): void {
    $account = Account::factory()->create();
    $order = vnpayRetryOrder($account);

    $this->actingAs($account, 'sanctum')
        ->getJson("/api/v1/account/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.payment_method', 'vnpay')
        ->assertJsonPath('data.payment_status', 'pending')
        ->assertJsonPath('data.can_pay', true)
        ->assertJsonPath('data.payment_expires_at', $order->payment_expires_at?->toIso8601String());
});

test('account order list exposes can_pay for unpaid vnpay pending order', function (): void {
    $account = Account::factory()->create();
    $order = vnpayRetryOrder($account);

    $this->actingAs($account, 'sanctum')
        ->getJson('/api/v1/account/orders')
        ->assertOk()
        ->assertJsonPath('data.0.id', $order->id)
        ->assertJsonPath('data.0.payment_method', 'vnpay')
        ->assertJsonPath('data.0.payment_status', 'pending')
        ->assertJsonPath('data.0.can_pay', true);
});

test('customer can regenerate vnpay payment url and expire stale pending transaction', function (): void {
    $account = Account::factory()->create();
    $order = vnpayRetryOrder($account);
    $service = app(VnPayService::class);

    $first = $service->createPaymentUrl($order, '127.0.0.1');
    $firstTxnId = PaymentTransaction::query()->where('order_id', $order->id)->value('id');

    $response = $this->actingAs($account, 'sanctum')
        ->postJson("/api/v1/account/orders/{$order->id}/vnpay/payment-url")
        ->assertOk()
        ->assertJsonPath('data.order_id', $order->id)
        ->assertJsonStructure([
            'data' => [
                'order_id',
                'payment_url',
                'payment_transaction_id',
                'vnp_TxnRef',
                'expires_at',
            ],
        ]);

    expect($response->json('data.payment_url'))->toContain('https://sandbox.vnpayment.vn/paymentv2/vpcpay.html?')
        ->and($response->json('data.vnp_TxnRef'))->not->toBe($first['vnp_TxnRef']);

    $firstTxn = PaymentTransaction::query()->findOrFail($firstTxnId);
    expect($firstTxn->status)->toBe(PaymentTransactionStatus::EXPIRED)
        ->and($firstTxn->payload['expired_reason'] ?? null)->toBe('customer_retry')
        ->and($firstTxn->completed_at)->not->toBeNull();

    $pendingTransactions = PaymentTransaction::query()
        ->where('order_id', $order->id)
        ->where('status', PaymentTransactionStatus::PENDING)
        ->get();

    expect($pendingTransactions)->toHaveCount(1)
        ->and($pendingTransactions->first()->id)->toBe($response->json('data.payment_transaction_id'))
        ->and($pendingTransactions->first()->gateway_txn_id)->toBe($response->json('data.vnp_TxnRef'));

    $order->refresh();
    expect($order->payment_status)->toBe(PaymentStatus::PENDING)
        ->and($order->payment_expires_at)->not->toBeNull()
        ->and($order->payment_expires_at->isFuture())->toBeTrue();
});

test('other customer cannot regenerate vnpay payment url', function (): void {
    $owner = Account::factory()->create();
    $other = Account::factory()->create();
    $order = vnpayRetryOrder($owner);

    $this->actingAs($other, 'sanctum')
        ->postJson("/api/v1/account/orders/{$order->id}/vnpay/payment-url")
        ->assertForbidden();
});

test('cannot regenerate vnpay payment url for paid order', function (): void {
    $account = Account::factory()->create();
    $order = vnpayRetryOrder($account);
    $order->update([
        'payment_status' => PaymentStatus::PAID,
        'current_status' => OrderStatus::CONFIRMED,
    ]);

    $this->actingAs($account, 'sanctum')
        ->postJson("/api/v1/account/orders/{$order->id}/vnpay/payment-url")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['order']);
});

test('cannot regenerate vnpay payment url when payment window expired', function (): void {
    $account = Account::factory()->create();
    $order = vnpayRetryOrder($account, now()->subMinute());

    $this->actingAs($account, 'sanctum')
        ->postJson("/api/v1/account/orders/{$order->id}/vnpay/payment-url")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['order']);
});

test('checkout idempotency still reuses valid vnpay payment url', function (): void {
    $order = vnpayRetryOrder(Account::factory()->create());
    $service = app(VnPayService::class);

    $first = $service->createPaymentUrl($order, '127.0.0.1');
    $second = $service->createPaymentUrl($order, '127.0.0.1');

    expect($second['vnp_TxnRef'])->toBe($first['vnp_TxnRef'])
        ->and($second['payment_transaction_id'])->toBe($first['payment_transaction_id'])
        ->and(PaymentTransaction::query()->where('order_id', $order->id)->count())->toBe(1);
});

test('force new vnpay payment always creates new transaction', function (): void {
    $order = vnpayRetryOrder(Account::factory()->create());
    $service = app(VnPayService::class);

    $first = $service->createPaymentUrl($order, '127.0.0.1');
    $second = $service->createPaymentUrl($order, '127.0.0.1', forceNew: true);

    expect($second['vnp_TxnRef'])->not->toBe($first['vnp_TxnRef']);

    $expired = PaymentTransaction::query()->findOrFail($first['payment_transaction_id']);
    expect($expired->status)->toBe(PaymentTransactionStatus::EXPIRED)
        ->and($expired->payload['expired_reason'] ?? null)->toBe('customer_retry')
        ->and($expired->completed_at)->not->toBeNull();

    expect(
        PaymentTransaction::query()
            ->where('order_id', $order->id)
            ->where('status', PaymentTransactionStatus::PENDING)
            ->count()
    )->toBe(1);
});

test('paid order exposes can_pay false', function (): void {
    $account = Account::factory()->create();
    $order = vnpayRetryOrder($account);
    $order->update([
        'payment_status' => PaymentStatus::PAID,
        'current_status' => OrderStatus::CONFIRMED,
    ]);

    $this->actingAs($account, 'sanctum')
        ->getJson("/api/v1/account/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.can_pay', false);
});

test('expired vnpay transaction callback after retry does not mark order paid', function (): void {
    $order = vnpayRetryOrder(Account::factory()->create());
    $service = app(VnPayService::class);

    $first = $service->createPaymentUrl($order, '127.0.0.1');
    $oldTxn = PaymentTransaction::query()->findOrFail($first['payment_transaction_id']);

    $service->createPaymentUrl($order, '127.0.0.1', forceNew: true);

    $oldTxn->refresh();
    expect($oldTxn->status)->toBe(PaymentTransactionStatus::EXPIRED);

    $result = $service->handleReturn(vnpaySignedSuccessReturn($service, $oldTxn, $order));

    expect($result['success'])->toBeFalse()
        ->and($result['message'])->toBe('Transaction is no longer active.');

    $order->refresh();
    $oldTxn->refresh();

    expect($order->payment_status)->toBe(PaymentStatus::PENDING)
        ->and($oldTxn->status)->toBe(PaymentTransactionStatus::EXPIRED);
});

test('second vnpay transaction callback is idempotent when order already paid', function (): void {
    $order = vnpayRetryOrder(Account::factory()->create());
    $service = app(VnPayService::class);

    $first = $service->createPaymentUrl($order, '127.0.0.1');
    $firstTxn = PaymentTransaction::query()->findOrFail($first['payment_transaction_id']);

    $paidResult = $service->handleReturn(vnpaySignedSuccessReturn($service, $firstTxn, $order));
    expect($paidResult['success'])->toBeTrue();

    $secondTxn = PaymentTransaction::query()->create([
        'order_id' => $order->id,
        'gateway' => PaymentGateway::VNPAY,
        'gateway_txn_id' => 'B'.$order->id.'T'.strtoupper(\Illuminate\Support\Str::random(10)),
        'type' => PaymentTransactionType::PAYMENT,
        'amount' => $order->final_amount,
        'status' => PaymentTransactionStatus::PENDING,
        'payload' => $firstTxn->payload,
    ]);

    $order->refresh();
    expect($order->payment_status)->toBe(PaymentStatus::PAID);

    $duplicateResult = $service->handleReturn(vnpaySignedSuccessReturn($service, $secondTxn, $order));

    expect($duplicateResult['success'])->toBeTrue()
        ->and($duplicateResult['idempotent'] ?? false)->toBeTrue()
        ->and($duplicateResult['payment_status'])->toBe('paid');

    $secondTxn->refresh();
    expect($secondTxn->status)->toBe(PaymentTransactionStatus::PENDING);

    $order->refresh();
    expect($order->payment_status)->toBe(PaymentStatus::PAID);
});

test('expired vnpay transaction callback is idempotent when order already paid', function (): void {
    $order = vnpayRetryOrder(Account::factory()->create());
    $service = app(VnPayService::class);

    $first = $service->createPaymentUrl($order, '127.0.0.1');
    $oldTxn = PaymentTransaction::query()->findOrFail($first['payment_transaction_id']);

    $service->createPaymentUrl($order, '127.0.0.1', forceNew: true);
    $newTxn = PaymentTransaction::query()
        ->where('order_id', $order->id)
        ->where('status', PaymentTransactionStatus::PENDING)
        ->firstOrFail();

    $service->handleReturn(vnpaySignedSuccessReturn($service, $newTxn, $order));

    $oldTxn->refresh();
    expect($oldTxn->status)->toBe(PaymentTransactionStatus::EXPIRED);

    $result = $service->handleReturn(vnpaySignedSuccessReturn($service, $oldTxn, $order));

    expect($result['success'])->toBeTrue()
        ->and($result['idempotent'] ?? false)->toBeTrue()
        ->and($result['payment_status'])->toBe('paid');

    $oldTxn->refresh();
    expect($oldTxn->status)->toBe(PaymentTransactionStatus::EXPIRED);
});
