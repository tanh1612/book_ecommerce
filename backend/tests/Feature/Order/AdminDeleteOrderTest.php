<?php

use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentMethod;
use App\Enums\Order\PaymentStatus;
use App\Models\Account;
use App\Models\Order;
use App\Models\ShippingMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function deleteTestOrder(OrderStatus $status, PaymentStatus $paymentStatus): Order
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
        'payment_status' => $paymentStatus,
        'note' => null,
        'current_status' => $status,
    ]);
}

test('order is admin deletable only when confirmed and payment pending', function (): void {
    expect(deleteTestOrder(OrderStatus::CONFIRMED, PaymentStatus::PENDING)->isAdminDeletable())->toBeTrue()
        ->and(deleteTestOrder(OrderStatus::CONFIRMED, PaymentStatus::PAID)->isAdminDeletable())->toBeFalse()
        ->and(deleteTestOrder(OrderStatus::PENDING, PaymentStatus::PENDING)->isAdminDeletable())->toBeFalse()
        ->and(deleteTestOrder(OrderStatus::PROCESSING, PaymentStatus::PENDING)->isAdminDeletable())->toBeFalse();
});
