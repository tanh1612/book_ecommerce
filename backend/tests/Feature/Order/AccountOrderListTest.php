<?php

use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentMethod;
use App\Enums\Order\PaymentStatus;
use App\Models\Account;
use App\Models\Book;
use App\Models\BookImage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ShippingMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function accountOrderListShipping(): ShippingMethod
{
    return ShippingMethod::query()->create([
        'name' => 'Test ship',
        'description' => null,
        'is_active' => true,
    ]);
}

function accountOrderListOrder(
    Account $account,
    OrderStatus $status = OrderStatus::CONFIRMED,
    float $finalAmount = 194000,
): Order {
    return Order::query()->create([
        'account_id' => $account->id,
        'shipping_method_id' => accountOrderListShipping()->id,
        'total_amount' => $finalAmount,
        'shipping_fee' => 0,
        'final_amount' => $finalAmount,
        'shipping_name' => 'Nguyen Van A',
        'shipping_phone' => '0900000000',
        'shipping_address' => '1 Test St',
        'payment_method' => PaymentMethod::COD,
        'payment_status' => PaymentStatus::PENDING,
        'note' => null,
        'current_status' => $status,
    ]);
}

function accountOrderListItem(Order $order, Book $book, int $quantity = 1, bool $isReviewed = false): OrderItem
{
    return OrderItem::query()->create([
        'order_id' => $order->id,
        'book_id' => $book->id,
        'promotion_id' => null,
        'price' => 194000,
        'quantity' => $quantity,
        'total_price' => 194000 * $quantity,
        'discount_amount' => 0,
        'is_reviewed' => $isReviewed,
    ]);
}

test('guest cannot list account orders', function (): void {
    $this->getJson('/api/v1/account/orders')
        ->assertUnauthorized();
});

test('authenticated user can list own orders', function (): void {
    $account = Account::factory()->create();
    $order = accountOrderListOrder($account);
    $book = Book::factory()->create(['name' => 'Test Book']);
    accountOrderListItem($order, $book);

    $this->actingAs($account, 'sanctum')
        ->getJson('/api/v1/account/orders')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                [
                    'id',
                    'current_status',
                    'created_at',
                    'total_quantity',
                    'final_amount',
                    'items',
                    'can_cancel',
                ],
            ],
            'links',
            'meta',
        ])
        ->assertJsonPath('data.0.id', $order->id)
        ->assertJsonPath('data.0.final_amount', 194000)
        ->assertJsonPath('data.0.items.0.book_name', 'Test Book');
});

test('user only sees own orders not other accounts', function (): void {
    $owner = Account::factory()->create();
    $other = Account::factory()->create();
    $ownOrder = accountOrderListOrder($owner);
    accountOrderListOrder($other);

    $this->actingAs($owner, 'sanctum')
        ->getJson('/api/v1/account/orders')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $ownOrder->id);
});

test('order list item shape excludes sensitive and internal fields', function (): void {
    $account = Account::factory()->create();
    $order = accountOrderListOrder($account);
    $book = Book::factory()->create();
    BookImage::factory()->create([
        'book_id' => $book->id,
        'image_url' => 'https://cdn.example.test/cover.jpg',
        'sort_order' => 0,
    ]);
    accountOrderListItem($order, $book);

    $response = $this->actingAs($account, 'sanctum')
        ->getJson('/api/v1/account/orders')
        ->assertOk();

    $row = $response->json('data.0');
    expect($row)->toHaveKeys([
        'id',
        'current_status',
        'created_at',
        'total_quantity',
        'final_amount',
        'items',
        'can_cancel',
    ])
        ->and($row)->not->toHaveKey('can_review')
        ->and($row)->not->toHaveKeys([
            'shipping_phone',
            'shipping_address',
            'payment_method',
            'payment_status',
            'cancel_block_reason',
            'timeline',
        ]);

    $item = $row['items'][0];
    expect($item)->toHaveKeys(['review_target_id', 'book_name', 'thumbnail_url', 'can_review'])
        ->and($item)->not->toHaveKeys(['book_id', 'order_item_id', 'quantity', 'price'])
        ->and($item['thumbnail_url'])->toBe('https://cdn.example.test/cover.jpg');
});

test('total quantity sums order item quantities', function (): void {
    $account = Account::factory()->create();
    $order = accountOrderListOrder($account);
    $bookA = Book::factory()->create();
    $bookB = Book::factory()->create();
    accountOrderListItem($order, $bookA, 2);
    accountOrderListItem($order, $bookB, 3);

    $this->actingAs($account, 'sanctum')
        ->getJson('/api/v1/account/orders')
        ->assertOk()
        ->assertJsonPath('data.0.total_quantity', 5);
});

test('status filter returns only matching orders', function (): void {
    $account = Account::factory()->create();
    $confirmed = accountOrderListOrder($account, OrderStatus::CONFIRMED);
    accountOrderListOrder($account, OrderStatus::CANCELLED);

    $this->actingAs($account, 'sanctum')
        ->getJson('/api/v1/account/orders?status=confirmed')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $confirmed->id)
        ->assertJsonPath('data.0.current_status', 'confirmed');
});

test('can cancel reflects customer cancel eligibility', function (): void {
    $account = Account::factory()->create();
    $cancellable = accountOrderListOrder($account, OrderStatus::CONFIRMED);
    accountOrderListOrder($account, OrderStatus::PROCESSING);

    $response = $this->actingAs($account, 'sanctum')
        ->getJson('/api/v1/account/orders')
        ->assertOk();

    $rows = collect($response->json('data'));
    $confirmedRow = $rows->firstWhere('id', $cancellable->id);
    $processingRow = $rows->firstWhere('current_status', 'processing');

    expect($confirmedRow['can_cancel'])->toBeTrue()
        ->and($processingRow['can_cancel'])->toBeFalse();
});

test('item can review is true when completed order has unreviewed item', function (): void {
    $account = Account::factory()->create();
    $order = accountOrderListOrder($account, OrderStatus::COMPLETED);
    $item = accountOrderListItem($order, Book::factory()->create(), 1, false);

    $this->actingAs($account, 'sanctum')
        ->getJson('/api/v1/account/orders')
        ->assertOk()
        ->assertJsonPath('data.0.items.0.review_target_id', $item->id)
        ->assertJsonPath('data.0.items.0.can_review', true);
});

test('item can review is false when order is not completed', function (): void {
    $account = Account::factory()->create();
    $order = accountOrderListOrder($account, OrderStatus::CONFIRMED);
    accountOrderListItem($order, Book::factory()->create(), 1, false);

    $this->actingAs($account, 'sanctum')
        ->getJson('/api/v1/account/orders')
        ->assertOk()
        ->assertJsonPath('data.0.items.0.can_review', false);
});

test('item can review is false when item already reviewed', function (): void {
    $account = Account::factory()->create();
    $order = accountOrderListOrder($account, OrderStatus::COMPLETED);
    accountOrderListItem($order, Book::factory()->create(), 1, true);

    $this->actingAs($account, 'sanctum')
        ->getJson('/api/v1/account/orders')
        ->assertOk()
        ->assertJsonPath('data.0.items.0.can_review', false);
});

test('invalid status filter returns validation error', function (): void {
    $account = Account::factory()->create();

    $this->actingAs($account, 'sanctum')
        ->getJson('/api/v1/account/orders?status=invalid')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});
