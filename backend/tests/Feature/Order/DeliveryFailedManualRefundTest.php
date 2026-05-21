<?php

use App\Enums\Account\AccountRole;
use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentMethod;
use App\Enums\Order\PaymentStatus;
use App\Enums\Payment\PaymentGateway;
use App\Enums\Payment\PaymentTransactionStatus;
use App\Enums\Payment\PaymentTransactionType;
use App\Models\Account;
use App\Models\Book;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderTimeline;
use App\Models\PaymentTransaction;
use App\Models\ShippingMethod;
use App\Models\Warehouse;
use App\Services\Order\OrderStatusTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function deliveryFailedBaseOrder(
    OrderStatus $status,
    PaymentMethod $paymentMethod,
    PaymentStatus $paymentStatus,
): Order {
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
        'shipping_fee' => 10000.00,
        'final_amount' => 110000.00,
        'shipping_name' => 'Nguyen Van A',
        'shipping_phone' => '0900000000',
        'shipping_address' => '1 Test St',
        'payment_method' => $paymentMethod,
        'payment_status' => $paymentStatus,
        'note' => null,
        'current_status' => $status,
    ]);
}

test('cod shipping pending delivery failed cancels order failed payment releases inventory', function (): void {
    $admin = Account::factory()->create();
    $order = deliveryFailedBaseOrder(OrderStatus::SHIPPING, PaymentMethod::COD, PaymentStatus::PENDING);
    $book = Book::factory()->create();
    Inventory::factory()->create([
        'book_id' => $book->id,
        'warehouse_id' => Warehouse::factory(),
        'quantity' => 10,
        'reserved_quantity' => 3,
    ]);
    OrderItem::query()->create([
        'order_id' => $order->id,
        'book_id' => $book->id,
        'promotion_id' => null,
        'price' => 50000,
        'quantity' => 3,
        'total_price' => 150000,
        'discount_amount' => 0,
        'is_reviewed' => false,
    ]);

    $updated = app(OrderStatusTransitionService::class)->markDeliveryFailed($order, $admin, null);

    expect($updated->current_status)->toBe(OrderStatus::CANCELLED)
        ->and($updated->payment_status)->toBe(PaymentStatus::CANCELLED);

    $inventory = Inventory::query()->where('book_id', $book->id)->firstOrFail();
    expect((int) $inventory->reserved_quantity)->toBe(0);

    $timeline = OrderTimeline::query()
        ->where('order_id', $order->id)
        ->where('status', OrderStatus::CANCELLED->value)
        ->latest('id')
        ->first();

    expect($timeline)->not->toBeNull()
        ->and($timeline->note)->toContain('Giao hàng thất bại');
});

test('vnpay shipping paid delivery failed sets refunding pending transaction and deadline', function (): void {
    config(['refund.manual_refund_deadline_days' => 15, 'refund.support_hotline' => '1900xxxx']);

    $admin = Account::factory()->create();
    $order = deliveryFailedBaseOrder(OrderStatus::SHIPPING, PaymentMethod::VNPAY, PaymentStatus::PAID);

    $updated = app(OrderStatusTransitionService::class)->markDeliveryFailed($order, $admin);

    expect($updated->current_status)->toBe(OrderStatus::CANCELLED)
        ->and($updated->payment_status)->toBe(PaymentStatus::REFUNDING)
        ->and($updated->refund_deadline_at)->not->toBeNull();

    $txn = PaymentTransaction::query()
        ->where('order_id', $order->id)
        ->where('type', PaymentTransactionType::REFUND)
        ->firstOrFail();

    expect($txn->gateway)->toBe(PaymentGateway::VNPAY)
        ->and($txn->status)->toBe(PaymentTransactionStatus::PENDING)
        ->and((string) $txn->amount)->toBe((string) $order->final_amount);
    expect($txn->payload['support_hotline'] ?? null)->toBe('1900xxxx');
});

test('admin can confirm manual refund marks transaction refunded', function (): void {
    config(['refund.manual_refund_deadline_days' => 15]);

    $admin = Account::factory()->create();
    $customer = Account::factory()->create();
    $order = deliveryFailedBaseOrder(OrderStatus::SHIPPING, PaymentMethod::VNPAY, PaymentStatus::PAID);
    $order->update(['account_id' => $customer->id]);

    $order = app(OrderStatusTransitionService::class)->markDeliveryFailed($order, $admin);

    $bankInfo = [
        'bank_code' => 'VCB',
        'bank_name' => 'Vietcombank',
        'bank_bin' => 970436,
        'account_number' => '123456789',
        'account_holder' => 'NGUYEN VAN A',
    ];
    app(OrderStatusTransitionService::class)->submitRefundBankInfo($order, $customer, $bankInfo);

    $updated = app(OrderStatusTransitionService::class)->confirmManualRefundCompleted(
        $order->fresh(),
        $admin,
        'TT-12345',
        now()->toDateString(),
        'Đã chuyển khoản',
    );

    expect($updated->payment_status)->toBe(PaymentStatus::REFUNDED)
        ->and($updated->refund_deadline_at)->toBeNull();

    $txn = PaymentTransaction::query()
        ->where('order_id', $order->id)
        ->where('type', PaymentTransactionType::REFUND)
        ->firstOrFail();

    expect($txn->status)->toBe(PaymentTransactionStatus::REFUNDED)
        ->and($txn->completed_at)->not->toBeNull()
        ->and($txn->payload['transfer_confirmation']['reference_code'] ?? null)->toBe('TT-12345')
        ->and($txn->payload['bank_info']['account_number'] ?? null)->toBe('123456789');
});

test('expire manual refund command closes order when past deadline', function (): void {
    $shipping = ShippingMethod::query()->create([
        'name' => 'Test ship',
        'description' => null,
        'is_active' => true,
    ]);
    $account = Account::factory()->create();
    $order = Order::query()->create([
        'account_id' => $account->id,
        'shipping_method_id' => $shipping->id,
        'total_amount' => 100000.00,
        'shipping_fee' => 0,
        'final_amount' => 100000.00,
        'shipping_name' => 'A',
        'shipping_phone' => '0900000000',
        'shipping_address' => 'Addr',
        'payment_method' => PaymentMethod::VNPAY,
        'payment_status' => PaymentStatus::REFUNDING,
        'note' => null,
        'current_status' => OrderStatus::CANCELLED,
        'refund_deadline_at' => now()->subDay(),
    ]);

    PaymentTransaction::query()->create([
        'order_id' => $order->id,
        'gateway' => PaymentGateway::VNPAY,
        'gateway_txn_id' => null,
        'type' => PaymentTransactionType::REFUND,
        'amount' => $order->final_amount,
        'status' => PaymentTransactionStatus::PENDING,
        'payload' => [],
    ]);

    $this->artisan('orders:expire-manual-refunds')->assertSuccessful();

    $order->refresh();
    expect($order->current_status)->toBe(OrderStatus::REFUND_CLOSED)
        ->and($order->payment_status)->toBe(PaymentStatus::REFUND_EXPIRED)
        ->and($order->refund_deadline_at)->toBeNull();

    $timeline = OrderTimeline::query()
        ->where('order_id', $order->id)
        ->where('status', OrderStatus::REFUND_CLOSED->value)
        ->latest('id')
        ->first();
    expect($timeline)->not->toBeNull()
        ->and($timeline->note)->toContain('quá hạn');
});

test('cannot mark delivery failed when not shipping', function (): void {
    $admin = Account::factory()->create();
    $order = deliveryFailedBaseOrder(OrderStatus::PROCESSING, PaymentMethod::COD, PaymentStatus::PENDING);

    app(OrderStatusTransitionService::class)->markDeliveryFailed($order, $admin);
})->throws(ValidationException::class);

test('cannot mark delivery failed for cod already paid', function (): void {
    $admin = Account::factory()->create();
    $order = deliveryFailedBaseOrder(OrderStatus::SHIPPING, PaymentMethod::COD, PaymentStatus::PAID);

    app(OrderStatusTransitionService::class)->markDeliveryFailed($order, $admin);
})->throws(ValidationException::class);

test('cannot mark delivery failed for vnpay not paid', function (): void {
    $admin = Account::factory()->create();
    $order = deliveryFailedBaseOrder(OrderStatus::SHIPPING, PaymentMethod::VNPAY, PaymentStatus::PENDING);

    app(OrderStatusTransitionService::class)->markDeliveryFailed($order, $admin);
})->throws(ValidationException::class);

test('does not duplicate pending refund transaction', function (): void {
    $admin = Account::factory()->create();
    $order = deliveryFailedBaseOrder(OrderStatus::SHIPPING, PaymentMethod::VNPAY, PaymentStatus::PAID);

    app(OrderStatusTransitionService::class)->markDeliveryFailed($order, $admin);

    app(OrderStatusTransitionService::class)->markDeliveryFailed($order->fresh(), $admin);
})->throws(ValidationException::class);

test('account can view own order with manual refund payload via api', function (): void {
    config(['refund.support_hotline' => '1900 1234']);

    $shipping = ShippingMethod::query()->create([
        'name' => 'Test ship',
        'description' => null,
        'is_active' => true,
    ]);
    $account = Account::factory()->create();
    $order = Order::query()->create([
        'account_id' => $account->id,
        'shipping_method_id' => $shipping->id,
        'total_amount' => 100000.00,
        'shipping_fee' => 0,
        'final_amount' => 100000.00,
        'shipping_name' => 'A',
        'shipping_phone' => '0900000000',
        'shipping_address' => 'Addr',
        'payment_method' => PaymentMethod::VNPAY,
        'payment_status' => PaymentStatus::REFUNDING,
        'note' => null,
        'current_status' => OrderStatus::CANCELLED,
        'refund_deadline_at' => now()->addDays(10),
    ]);

    $response = $this->actingAs($account, 'sanctum')
        ->getJson("/api/v1/account/orders/{$order->id}")
        ->assertOk();

    $response->assertJsonPath('data.manual_refund.support_hotline', '1900 1234');
    expect((float) $response->json('data.manual_refund.refund_amount'))->toBe(100000.0);
});

test('admin can view any order via policy', function (): void {
    $shipping = ShippingMethod::query()->create([
        'name' => 'Test ship',
        'description' => null,
        'is_active' => true,
    ]);
    $owner = Account::factory()->create();
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);
    $order = Order::query()->create([
        'account_id' => $owner->id,
        'shipping_method_id' => $shipping->id,
        'total_amount' => 100000.00,
        'shipping_fee' => 0,
        'final_amount' => 100000.00,
        'shipping_name' => 'A',
        'shipping_phone' => '0900000000',
        'shipping_address' => 'Addr',
        'payment_method' => PaymentMethod::COD,
        'payment_status' => PaymentStatus::PENDING,
        'note' => null,
        'current_status' => OrderStatus::CONFIRMED,
    ]);

    expect($admin->can('view', $order))->toBeTrue();
});

test('account cannot view another users order', function (): void {
    $shipping = ShippingMethod::query()->create([
        'name' => 'Test ship',
        'description' => null,
        'is_active' => true,
    ]);
    $owner = Account::factory()->create();
    $other = Account::factory()->create();
    $order = Order::query()->create([
        'account_id' => $owner->id,
        'shipping_method_id' => $shipping->id,
        'total_amount' => 100000.00,
        'shipping_fee' => 0,
        'final_amount' => 100000.00,
        'shipping_name' => 'A',
        'shipping_phone' => '0900000000',
        'shipping_address' => 'Addr',
        'payment_method' => PaymentMethod::COD,
        'payment_status' => PaymentStatus::PENDING,
        'note' => null,
        'current_status' => OrderStatus::CONFIRMED,
    ]);

    $this->actingAs($other, 'sanctum')
        ->getJson("/api/v1/account/orders/{$order->id}")
        ->assertForbidden();
});
