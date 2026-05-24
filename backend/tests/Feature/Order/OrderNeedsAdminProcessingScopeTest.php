<?php

use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentMethod;
use App\Enums\Order\PaymentStatus;
use App\Models\Account;
use App\Models\Order;
use App\Models\ShippingMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function orderForProcessingScopeTest(OrderStatus $status): Order
{
    $shipping = ShippingMethod::query()->create([
        'name' => 'Test ship',
        'description' => null,
        'is_active' => true,
    ]);

    return Order::query()->create([
        'account_id' => Account::factory()->create()->id,
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
        'current_status' => $status,
    ]);
}

test('needs admin processing scope includes active fulfillment statuses only', function (): void {
    $pending = orderForProcessingScopeTest(OrderStatus::PENDING);
    $confirmed = orderForProcessingScopeTest(OrderStatus::CONFIRMED);
    $processing = orderForProcessingScopeTest(OrderStatus::PROCESSING);
    $shipping = orderForProcessingScopeTest(OrderStatus::SHIPPING);
    $completed = orderForProcessingScopeTest(OrderStatus::COMPLETED);
    $cancelled = orderForProcessingScopeTest(OrderStatus::CANCELLED);
    $refundClosed = orderForProcessingScopeTest(OrderStatus::REFUND_CLOSED);

    $ids = Order::query()->needsAdminProcessing()->pluck('id')->all();

    expect($ids)->toContain($pending->id, $confirmed->id, $processing->id, $shipping->id)
        ->and($ids)->not->toContain($completed->id, $cancelled->id, $refundClosed->id);
});
