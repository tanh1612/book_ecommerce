<?php

use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentMethod;
use App\Enums\Order\PaymentStatus;
use App\Models\Account;
use App\Models\Order;
use App\Models\OrderTimeline;
use App\Models\ShippingMethod;
use App\Services\Statistics\RevenueReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createRevenueOrder(
    OrderStatus $status,
    PaymentStatus $paymentStatus,
    float $finalAmount,
    float $shippingFee,
    ?Carbon $completedAt = null,
): Order {
    $shipping = ShippingMethod::query()->first() ?? ShippingMethod::query()->create([
        'name' => 'Test ship',
        'description' => null,
        'is_active' => true,
    ]);

    $order = Order::query()->create([
        'account_id' => Account::factory()->create()->id,
        'shipping_method_id' => $shipping->id,
        'total_amount' => $finalAmount - $shippingFee,
        'shipping_fee' => $shippingFee,
        'final_amount' => $finalAmount,
        'shipping_name' => 'A',
        'shipping_phone' => '0900000000',
        'shipping_address' => 'Addr',
        'payment_method' => PaymentMethod::VNPAY,
        'payment_status' => $paymentStatus,
        'note' => null,
        'current_status' => $status,
    ]);

    if ($completedAt !== null) {
        OrderTimeline::query()->create([
            'order_id' => $order->id,
            'status' => OrderStatus::COMPLETED->value,
            'note' => 'Test completed',
            'actor' => 'system',
            'created_at' => $completedAt,
        ]);
    }

    return $order;
}

test('revenue report only counts completed and paid orders by completion timeline date', function (): void {
    $service = app(RevenueReportService::class);
    $completionDay = Carbon::parse('2026-05-10 15:30:00');

    createRevenueOrder(OrderStatus::COMPLETED, PaymentStatus::PAID, 300000, 10000, $completionDay);

    createRevenueOrder(OrderStatus::CONFIRMED, PaymentStatus::PAID, 200000, 0, $completionDay);
    createRevenueOrder(OrderStatus::SHIPPING, PaymentStatus::PAID, 150000, 0, $completionDay);
    createRevenueOrder(OrderStatus::CANCELLED, PaymentStatus::PAID, 100000, 0, $completionDay);

    $completedUnpaid = createRevenueOrder(OrderStatus::COMPLETED, PaymentStatus::PENDING, 500000, 0, $completionDay);

    $wrongDay = createRevenueOrder(
        OrderStatus::COMPLETED,
        PaymentStatus::PAID,
        400000,
        0,
        Carbon::parse('2026-05-01 10:00:00'),
    );

    Order::query()->whereKey($completedUnpaid->id)->update(['created_at' => Carbon::parse('2026-05-10')]);
    Order::query()->whereKey($wrongDay->id)->update(['created_at' => Carbon::parse('2026-05-10')]);

    $summary = $service->dailyRevenue(Carbon::parse('2026-05-10'));

    expect($summary->totalRevenue)->toBe(300000.0)
        ->and($summary->orderCount)->toBe(1)
        ->and($summary->averageOrderValue)->toBe(300000.0)
        ->and($summary->totalShippingFee)->toBe(10000.0);
});

test('revenue report aggregates monthly and yearly totals from completion date', function (): void {
    $service = app(RevenueReportService::class);

    createRevenueOrder(
        OrderStatus::COMPLETED,
        PaymentStatus::PAID,
        100000,
        5000,
        Carbon::parse('2026-03-05 12:00:00'),
    );
    createRevenueOrder(
        OrderStatus::COMPLETED,
        PaymentStatus::PAID,
        250000,
        10000,
        Carbon::parse('2026-03-20 08:00:00'),
    );
    createRevenueOrder(
        OrderStatus::COMPLETED,
        PaymentStatus::PAID,
        90000,
        0,
        Carbon::parse('2026-04-02 08:00:00'),
    );

    $march = $service->monthlyRevenue(2026, 3);
    $year = $service->yearlyRevenue(2026);

    expect($march->totalRevenue)->toBe(350000.0)
        ->and($march->orderCount)->toBe(2)
        ->and($year->totalRevenue)->toBe(440000.0)
        ->and($year->orderCount)->toBe(3);
});

test('revenue daily and monthly series fill missing periods with zero', function (): void {
    $service = app(RevenueReportService::class);

    createRevenueOrder(
        OrderStatus::COMPLETED,
        PaymentStatus::PAID,
        120000,
        0,
        Carbon::parse('2026-06-02 10:00:00'),
    );

    $daily = $service->dailySeries(
        Carbon::parse('2026-06-01'),
        Carbon::parse('2026-06-03'),
    );

    expect($daily)->toHaveCount(3)
        ->and($daily[0]->revenue)->toBe(0.0)
        ->and($daily[1]->revenue)->toBe(120000.0)
        ->and($daily[2]->revenue)->toBe(0.0);

    createRevenueOrder(
        OrderStatus::COMPLETED,
        PaymentStatus::PAID,
        80000,
        0,
        Carbon::parse('2026-02-15 10:00:00'),
    );

    $monthly = $service->monthlySeries(2026);

    expect($monthly)->toHaveCount(12)
        ->and($monthly[1]->revenue)->toBe(80000.0)
        ->and($monthly[5]->revenue)->toBe(120000.0)
        ->and($monthly[0]->revenue)->toBe(0.0);
});
