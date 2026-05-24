<?php

use App\Enums\Order\OrderStatus;
use App\Filament\Widgets\BestSellingBooks;
use App\Filament\Widgets\OrderStatusChart;
use App\Models\Account;
use App\Models\Book;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\ShippingMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function dashboardStatsOrder(OrderStatus $status): Order
{
    $shipping = ShippingMethod::query()->first() ?? ShippingMethod::query()->create([
        'name' => 'Test ship',
        'description' => null,
        'is_active' => true,
    ]);

    return Order::query()->create([
        'account_id' => Account::factory()->create()->id,
        'shipping_method_id' => $shipping->id,
        'total_amount' => 100000,
        'shipping_fee' => 0,
        'final_amount' => 100000,
        'shipping_name' => 'A',
        'shipping_phone' => '0900000000',
        'shipping_address' => 'Addr',
        'payment_method' => null,
        'payment_status' => null,
        'note' => null,
        'current_status' => $status,
    ]);
}

test('order status chart aggregates orders by current status', function (): void {
    dashboardStatsOrder(OrderStatus::PENDING);
    dashboardStatsOrder(OrderStatus::PENDING);
    dashboardStatsOrder(OrderStatus::COMPLETED);
    dashboardStatsOrder(OrderStatus::CANCELLED);

    $component = Livewire::test(OrderStatusChart::class)
        ->assertSee('Đơn hàng theo trạng thái')
        ->instance();

    $method = new ReflectionMethod(OrderStatusChart::class, 'getData');
    $method->setAccessible(true);

    $data = $method->invoke($component);
    $counts = array_combine($data['labels'], $data['datasets'][0]['data']);

    expect($counts[OrderStatus::PENDING->getLabel()])->toBe(2)
        ->and($counts[OrderStatus::COMPLETED->getLabel()])->toBe(1)
        ->and($counts[OrderStatus::CANCELLED->getLabel()])->toBe(1)
        ->and($counts[OrderStatus::PROCESSING->getLabel()])->toBe(0);
});

test('best selling books widget shows inventories ordered by sold quantity', function (): void {
    $first = Book::factory()->create(['name' => 'Sách bán chạy nhất', 'sku' => 'BEST-001']);
    $second = Book::factory()->create(['name' => 'Sách bán tốt', 'sku' => 'BEST-002']);
    $hidden = Book::factory()->create(['name' => 'Sách chưa bán', 'sku' => 'BEST-003']);

    Inventory::factory()->create([
        'book_id' => $second->id,
        'quantity' => 8,
        'reserved_quantity' => 1,
        'sold_quantity' => 7,
        'location_code' => 'B2',
    ]);
    Inventory::factory()->create([
        'book_id' => $first->id,
        'quantity' => 5,
        'reserved_quantity' => 0,
        'sold_quantity' => 12,
        'location_code' => 'A1',
    ]);
    Inventory::factory()->create([
        'book_id' => $hidden->id,
        'quantity' => 20,
        'reserved_quantity' => 0,
        'sold_quantity' => 0,
        'location_code' => 'C3',
    ]);

    Livewire::test(BestSellingBooks::class)
        ->assertSee('Sách bán chạy nhất')
        ->assertSeeInOrder(['Sách bán chạy nhất', '12', 'Sách bán tốt', '7'])
        ->assertDontSee('Sách chưa bán');
});
