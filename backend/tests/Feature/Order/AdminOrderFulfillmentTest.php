<?php

use App\Enums\Account\AccountRole;
use App\Enums\Order\OrderStatus;
use App\Filament\Resources\OrderResource\Pages\ViewOrder;
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
use App\Services\Order\OrderInvoiceService;
use App\Services\Order\OrderStatusTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function fulfillmentConfirmedTimeline(Order $order, ?\Illuminate\Support\Carbon $createdAt = null): void
{
    OrderTimeline::query()->create([
        'order_id' => $order->id,
        'status' => OrderStatus::CONFIRMED->value,
        'note' => OrderStatusTransitionService::TIMELINE_NOTE_CHECKOUT_COD,
        'actor' => 'system',
        'created_at' => $createdAt ?? now(),
        'updated_at' => $createdAt ?? now(),
    ]);
}

function fulfillmentTestOrder(
    OrderStatus $status,
    PaymentMethod $paymentMethod = PaymentMethod::COD,
    PaymentStatus $paymentStatus = PaymentStatus::PENDING,
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

test('admin can process confirmed order', function (): void {
    $admin = Account::factory()->create();
    $order = fulfillmentTestOrder(OrderStatus::CONFIRMED);
    fulfillmentConfirmedTimeline($order, now()->subMinutes(31));

    $updated = app(OrderStatusTransitionService::class)->processOrder($order, $admin, 'Bắt đầu đóng gói');

    expect($updated->current_status)->toBe(OrderStatus::PROCESSING);

    $timeline = OrderTimeline::query()
        ->where('order_id', $order->id)
        ->where('status', OrderStatus::PROCESSING->value)
        ->latest('id')
        ->first();

    expect($timeline)->not->toBeNull()
        ->and($timeline->note)->toBe('Bắt đầu đóng gói')
        ->and($timeline->actor)->toBe($admin->email);
});

test('admin can ship processing order', function (): void {
    $admin = Account::factory()->create();
    $order = fulfillmentTestOrder(OrderStatus::PROCESSING);

    $updated = app(OrderStatusTransitionService::class)->shipOrder($order, $admin);

    expect($updated->current_status)->toBe(OrderStatus::SHIPPING);

    $timeline = OrderTimeline::query()
        ->where('order_id', $order->id)
        ->where('status', OrderStatus::SHIPPING->value)
        ->latest('id')
        ->first();

    expect($timeline)->not->toBeNull()
        ->and($timeline->actor)->toBe($admin->email);
});

test('admin can confirm cod payment while shipping', function (): void {
    $admin = Account::factory()->create();
    $order = fulfillmentTestOrder(OrderStatus::SHIPPING, PaymentMethod::COD, PaymentStatus::PENDING);

    $updated = app(OrderStatusTransitionService::class)->confirmCodPayment($order, $admin);

    expect($updated->current_status)->toBe(OrderStatus::SHIPPING)
        ->and($updated->payment_status)->toBe(PaymentStatus::PAID);

    $timeline = OrderTimeline::query()
        ->where('order_id', $order->id)
        ->where('status', OrderStatus::SHIPPING->value)
        ->latest('id')
        ->first();

    expect($timeline)->not->toBeNull()
        ->and($timeline->note)->toBe('Xác nhận đã thu tiền COD. Tổng tiền: 110.000 đ.')
        ->and($timeline->actor)->toBe($admin->email);
});

test('admin can deliver paid shipping order and updates inventory sold quantity', function (): void {
    $admin = Account::factory()->create();
    $order = fulfillmentTestOrder(OrderStatus::SHIPPING, PaymentMethod::COD, PaymentStatus::PAID);
    $book = Book::factory()->create();
    Inventory::factory()->create([
        'book_id' => $book->id,
        'warehouse_id' => Warehouse::factory(),
        'quantity' => 10,
        'reserved_quantity' => 2,
        'sold_quantity' => 0,
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

    $updated = app(OrderStatusTransitionService::class)->deliverOrder($order, $admin);

    expect($updated->current_status)->toBe(OrderStatus::COMPLETED);

    $inventory = Inventory::query()->where('book_id', $book->id)->firstOrFail();
    expect((int) $inventory->quantity)->toBe(8)
        ->and((int) $inventory->reserved_quantity)->toBe(0)
        ->and((int) $inventory->sold_quantity)->toBe(2);

    $timeline = OrderTimeline::query()
        ->where('order_id', $order->id)
        ->where('status', OrderStatus::COMPLETED->value)
        ->latest('id')
        ->first();

    expect($timeline)->not->toBeNull()
        ->and($timeline->actor)->toBe($admin->email);
});

test('vnpay paid order can be delivered without cod confirmation', function (): void {
    $admin = Account::factory()->create();
    $order = fulfillmentTestOrder(
        OrderStatus::SHIPPING,
        PaymentMethod::VNPAY,
        PaymentStatus::PAID,
    );

    $updated = app(OrderStatusTransitionService::class)->deliverOrder($order, $admin);

    expect($updated->current_status)->toBe(OrderStatus::COMPLETED);
});

test('cod order cannot be delivered before payment confirmation', function (): void {
    $admin = Account::factory()->create();
    $order = fulfillmentTestOrder(OrderStatus::SHIPPING, PaymentMethod::COD, PaymentStatus::PENDING);

    app(OrderStatusTransitionService::class)->deliverOrder($order, $admin);
})->throws(ValidationException::class);

test('cannot confirm cod payment for vnpay order', function (): void {
    $admin = Account::factory()->create();
    $order = fulfillmentTestOrder(
        OrderStatus::SHIPPING,
        PaymentMethod::VNPAY,
        PaymentStatus::PENDING,
    );

    app(OrderStatusTransitionService::class)->confirmCodPayment($order, $admin);
})->throws(ValidationException::class);

test('cannot process order that is not confirmed', function (): void {
    $admin = Account::factory()->create();
    $order = fulfillmentTestOrder(OrderStatus::PROCESSING);

    app(OrderStatusTransitionService::class)->processOrder($order, $admin);
})->throws(ValidationException::class);

test('admin cannot process cod confirmed order within grace period', function (): void {
    $admin = Account::factory()->create();
    $order = fulfillmentTestOrder(OrderStatus::CONFIRMED);
    fulfillmentConfirmedTimeline($order, now()->subMinutes(10));

    app(OrderStatusTransitionService::class)->processOrder($order, $admin);
})->throws(ValidationException::class);

test('admin can process cod confirmed order after grace period', function (): void {
    $admin = Account::factory()->create();
    $order = fulfillmentTestOrder(OrderStatus::CONFIRMED);
    fulfillmentConfirmedTimeline($order, now()->subMinutes(31));

    $updated = app(OrderStatusTransitionService::class)->processOrder($order, $admin);

    expect($updated->current_status)->toBe(OrderStatus::PROCESSING);
});

test('admin cannot process cod confirmed order when confirmed timeline has null created_at within grace period', function (): void {
    $admin = Account::factory()->create();
    $order = fulfillmentTestOrder(OrderStatus::CONFIRMED);
    $order->forceFill(['created_at' => now()->subMinutes(10)])->save();

    OrderTimeline::query()->create([
        'order_id' => $order->id,
        'status' => OrderStatus::CONFIRMED->value,
        'note' => OrderStatusTransitionService::TIMELINE_NOTE_CHECKOUT_COD,
        'actor' => 'system',
        'created_at' => null,
        'updated_at' => null,
    ]);

    app(OrderStatusTransitionService::class)->processOrder($order->fresh(), $admin);
})->throws(ValidationException::class);

test('admin cannot process cod confirmed order without confirmed timeline within grace period', function (): void {
    $admin = Account::factory()->create();
    $order = fulfillmentTestOrder(OrderStatus::CONFIRMED);
    $order->forceFill(['created_at' => now()->subMinutes(10)])->save();

    expect(OrderTimeline::query()->where('order_id', $order->id)->where('status', OrderStatus::CONFIRMED->value)->exists())
        ->toBeFalse();

    app(OrderStatusTransitionService::class)->processOrder($order->fresh(), $admin);
})->throws(ValidationException::class);

test('admin can process cod confirmed order without confirmed timeline after grace period', function (): void {
    $admin = Account::factory()->create();
    $order = fulfillmentTestOrder(OrderStatus::CONFIRMED);
    $order->forceFill(['created_at' => now()->subMinutes(31)])->save();

    $updated = app(OrderStatusTransitionService::class)->processOrder($order->fresh(), $admin);

    expect($updated->current_status)->toBe(OrderStatus::PROCESSING);
});

test('filament process order action is disabled for cod confirmed order within grace without timeline', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);
    $order = fulfillmentTestOrder(OrderStatus::CONFIRMED);
    $order->forceFill(['created_at' => now()->subMinutes(10)])->save();

    Livewire::actingAs($admin)
        ->test(ViewOrder::class, ['record' => $order->getKey()])
        ->assertActionDisabled('processOrder');
});

test('filament process order action succeeds after grace without confirmed timeline', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);
    $order = fulfillmentTestOrder(OrderStatus::CONFIRMED);
    $order->forceFill(['created_at' => now()->subMinutes(31)])->save();

    Livewire::actingAs($admin)
        ->test(ViewOrder::class, ['record' => $order->getKey()])
        ->assertActionEnabled('processOrder')
        ->callAction('processOrder', data: ['note' => 'Bắt đầu xử lý'])
        ->assertNotified();

    expect($order->fresh()->current_status)->toBe(OrderStatus::PROCESSING);
});

test('admin can download invoice pdf route for processing order', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);
    $order = fulfillmentTestOrder(OrderStatus::PROCESSING);

    $this->actingAs($admin)
        ->get(route('admin.orders.invoice', ['order' => $order]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('invoice service renders pdf html for order with items', function (): void {
    $order = fulfillmentTestOrder(OrderStatus::PROCESSING);
    $book = Book::factory()->create(['name' => 'Sách thử nghiệm']);
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

    $order = $order->fresh(['items.book']);
    $order->final_amount = 250000;
    $html = app(OrderInvoiceService::class)->renderHtml($order);

    expect($html)
        ->toContain('HÓA ĐƠN BÁN HÀNG')
        ->toContain('Sách thử nghiệm')
        ->toContain('Nguyen Van A')
        ->toContain('1 Test St')
        ->toContain('customer-info')
        ->toContain('words-info')
        ->toContain('Hai trăm năm mươi nghìn đồng')
        ->toContain('DejaVu Sans');
});
