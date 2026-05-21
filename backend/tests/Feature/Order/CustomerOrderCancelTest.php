<?php

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

function customerCancelTestOrder(
    Account $account,
    OrderStatus $status,
    PaymentMethod $paymentMethod = PaymentMethod::COD,
    PaymentStatus $paymentStatus = PaymentStatus::PENDING,
): Order {
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
        'payment_method' => $paymentMethod,
        'payment_status' => $paymentStatus,
        'note' => null,
        'current_status' => $status,
    ]);
}

test('customer can cancel cod confirmed order via api', function (): void {
    $account = Account::factory()->create();
    $order = customerCancelTestOrder($account, OrderStatus::CONFIRMED);
    $book = Book::factory()->create();
    Inventory::factory()->create([
        'book_id' => $book->id,
        'warehouse_id' => Warehouse::factory(),
        'quantity' => 10,
        'reserved_quantity' => 2,
    ]);
    OrderItem::query()->create([
        'order_id' => $order->id,
        'book_id' => $book->id,
        'promotion_id' => null,
        'price' => 50000,
        'quantity' => 2,
        'total_price' => 100000,
        'discount_amount' => 0,
        'is_reviewed' => false,
    ]);

    $this->actingAs($account, 'sanctum')
        ->postJson("/api/v1/account/orders/{$order->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.current_status', OrderStatus::CANCELLED->value)
        ->assertJsonPath('data.can_cancel', false)
        ->assertJsonPath('data.payment_status', PaymentStatus::CANCELLED->value);

    $inventory = Inventory::query()->where('book_id', $book->id)->first();
    expect($inventory->reserved_quantity)->toBe(0);

    $timeline = OrderTimeline::query()
        ->where('order_id', $order->id)
        ->where('status', OrderStatus::CANCELLED->value)
        ->latest('id')
        ->first();

    expect($timeline)->not->toBeNull()
        ->and($timeline->actor)->toBe($account->email);
});

test('customer cannot cancel cod processing order', function (): void {
    $account = Account::factory()->create();
    $order = customerCancelTestOrder($account, OrderStatus::PROCESSING);

    $this->actingAs($account, 'sanctum')
        ->postJson("/api/v1/account/orders/{$order->id}/cancel")
        ->assertStatus(422);
});

test('customer can cancel unpaid vnpay pending order', function (): void {
    $account = Account::factory()->create();
    $order = customerCancelTestOrder(
        $account,
        OrderStatus::PENDING,
        PaymentMethod::VNPAY,
        PaymentStatus::PENDING,
    );

    PaymentTransaction::query()->create([
        'order_id' => $order->id,
        'gateway' => PaymentGateway::VNPAY,
        'gateway_txn_id' => 'VNP_TEST_1',
        'type' => PaymentTransactionType::PAYMENT,
        'amount' => $order->final_amount,
        'status' => PaymentTransactionStatus::PENDING,
        'payload' => [],
    ]);

    $updated = app(OrderStatusTransitionService::class)->cancelByCustomer($order, $account);

    expect($updated->current_status)->toBe(OrderStatus::CANCELLED)
        ->and($updated->payment_status)->toBe(PaymentStatus::CANCELLED);

    $txn = PaymentTransaction::query()->where('order_id', $order->id)->first();
    expect($txn->status)->toBe(PaymentTransactionStatus::CANCELLED);
});

test('customer cannot cancel paid vnpay order', function (): void {
    $account = Account::factory()->create();
    $order = customerCancelTestOrder(
        $account,
        OrderStatus::CONFIRMED,
        PaymentMethod::VNPAY,
        PaymentStatus::PAID,
    );

    app(OrderStatusTransitionService::class)->cancelByCustomer($order, $account);
})->throws(ValidationException::class);

test('customer cannot cancel another customers order', function (): void {
    $owner = Account::factory()->create();
    $other = Account::factory()->create();
    $order = customerCancelTestOrder($owner, OrderStatus::CONFIRMED);

    $this->actingAs($other, 'sanctum')
        ->postJson("/api/v1/account/orders/{$order->id}/cancel")
        ->assertForbidden();
});

test('account order resource exposes cancel eligibility', function (): void {
    $account = Account::factory()->create();
    $order = customerCancelTestOrder($account, OrderStatus::CONFIRMED);

    $this->actingAs($account, 'sanctum')
        ->getJson("/api/v1/account/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.can_cancel', true)
        ->assertJsonPath('data.cancel_block_reason', null);

    $processing = customerCancelTestOrder($account, OrderStatus::PROCESSING);

    $this->actingAs($account, 'sanctum')
        ->getJson("/api/v1/account/orders/{$processing->id}")
        ->assertOk()
        ->assertJsonPath('data.can_cancel', false)
        ->assertJsonPath('data.cancel_block_reason', 'Đơn đang được xử lý hoặc đã giao, không thể hủy.');
});
