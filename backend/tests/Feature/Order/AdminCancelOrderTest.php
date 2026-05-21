<?php

use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentMethod;
use App\Enums\Order\PaymentStatus;
use App\Models\Account;
use App\Models\Book;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderTimeline;
use App\Models\ShippingMethod;
use App\Models\Warehouse;
use App\Services\Order\OrderStatusTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function cancelTestOrder(OrderStatus $status): Order
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
        'payment_method' => PaymentMethod::COD,
        'payment_status' => PaymentStatus::PENDING,
        'note' => null,
        'current_status' => $status,
    ]);
}

test('admin can cancel confirmed order and release reserved inventory', function (): void {
    $admin = Account::factory()->create();
    $order = cancelTestOrder(OrderStatus::CONFIRMED);
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

    $updated = app(OrderStatusTransitionService::class)->cancelConfirmedOrder(
        $order,
        $admin,
        'Khách yêu cầu hủy',
    );

    expect($updated->current_status)->toBe(OrderStatus::CANCELLED)
        ->and($updated->payment_status)->toBe(PaymentStatus::CANCELLED);

    $inventory = Inventory::query()->where('book_id', $book->id)->first();
    expect($inventory->reserved_quantity)->toBe(0);

    $timeline = OrderTimeline::query()
        ->where('order_id', $order->id)
        ->where('status', OrderStatus::CANCELLED->value)
        ->latest('id')
        ->first();

    expect($timeline)->not->toBeNull()
        ->and($timeline->note)->toBe('Khách yêu cầu hủy')
        ->and($timeline->actor)->toBe($admin->email);
});

test('cannot cancel order that is not confirmed', function (OrderStatus $status): void {
    $admin = Account::factory()->create();
    $order = cancelTestOrder($status);

    app(OrderStatusTransitionService::class)->cancelConfirmedOrder($order, $admin);
})->throws(ValidationException::class)
    ->with([
        OrderStatus::PENDING,
        OrderStatus::PROCESSING,
        OrderStatus::SHIPPING,
        OrderStatus::COMPLETED,
        OrderStatus::CANCELLED,
    ]);
