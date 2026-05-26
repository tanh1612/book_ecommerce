<?php

use App\Enums\Account\AccountRole;
use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentMethod;
use App\Enums\Order\PaymentStatus;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Models\Account;
use App\Models\Order;
use App\Models\ShippingMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('order list tabs count and scope records by order status', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);
    $customer = Account::factory()->create(['role' => AccountRole::Customer]);
    $shippingMethod = ShippingMethod::query()->create([
        'name' => 'Giao hàng test',
        'description' => null,
        'is_active' => true,
    ]);

    $orders = collect(OrderStatus::cases())->mapWithKeys(
        fn (OrderStatus $status): array => [
            $status->value => Order::query()->create([
                'account_id' => $customer->id,
                'shipping_method_id' => $shippingMethod->id,
                'total_amount' => 100000,
                'shipping_fee' => 0,
                'final_amount' => 100000,
                'shipping_name' => 'Khách kiểm thử',
                'shipping_phone' => '0900000000',
                'shipping_address' => 'Địa chỉ kiểm thử',
                'payment_method' => PaymentMethod::COD,
                'payment_status' => PaymentStatus::PENDING,
                'current_status' => $status,
            ]),
        ],
    );

    $tabs = app(ListOrders::class)->getTabs();

    expect($tabs['all']->getLabel())->toBe('Tất cả')
        ->and($tabs['all']->getBadge())->toBe(count(OrderStatus::cases()))
        ->and($tabs['all']->getBadgeColor())->toBe('primary');

    foreach (OrderStatus::cases() as $status) {
        expect($tabs[$status->value]->getLabel())->toBe($status->getLabel())
            ->and($tabs[$status->value]->getBadge())->toBe(1)
            ->and($tabs[$status->value]->getBadgeColor())->toBe($status->getColor())
            ->and($tabs[$status->value]->isBadgeDeferred())->toBeTrue();
    }

    Livewire::actingAs($admin)
        ->test(ListOrders::class)
        ->set('activeTab', OrderStatus::COMPLETED->value)
        ->assertCanSeeTableRecords([$orders[OrderStatus::COMPLETED->value]])
        ->assertCanNotSeeTableRecords($orders->except(OrderStatus::COMPLETED->value));
});
