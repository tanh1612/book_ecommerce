<?php

use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentMethod;
use App\Enums\Order\PaymentStatus;
use App\Enums\Review\ReviewStatus;
use App\Models\Account;
use App\Models\Book;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Review;
use App\Models\ShippingMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function reviewTestShipping(): ShippingMethod
{
    return ShippingMethod::query()->create([
        'name' => 'Test ship',
        'description' => null,
        'is_active' => true,
    ]);
}

function reviewTestOrder(Account $account, OrderStatus $status = OrderStatus::COMPLETED): Order
{
    return Order::query()->create([
        'account_id' => $account->id,
        'shipping_method_id' => reviewTestShipping()->id,
        'total_amount' => 194000,
        'shipping_fee' => 0,
        'final_amount' => 194000,
        'shipping_name' => 'Nguyen Van A',
        'shipping_phone' => '0900000000',
        'shipping_address' => '1 Test St',
        'payment_method' => PaymentMethod::COD,
        'payment_status' => PaymentStatus::PAID,
        'note' => null,
        'current_status' => $status,
    ]);
}

function reviewTestOrderItem(Order $order, Book $book, bool $isReviewed = false): OrderItem
{
    return OrderItem::query()->create([
        'order_id' => $order->id,
        'book_id' => $book->id,
        'promotion_id' => null,
        'price' => 194000,
        'quantity' => 1,
        'total_price' => 194000,
        'discount_amount' => 0,
        'is_reviewed' => $isReviewed,
    ]);
}

test('user can submit review for own completed order item', function (): void {
    $account = Account::factory()->create();
    $order = reviewTestOrder($account);
    $book = Book::factory()->create();
    $item = reviewTestOrderItem($order, $book);

    $this->actingAs($account, 'web')
        ->postJson("/api/v1/account/order-items/{$item->id}/review", [
            'rating' => 5,
            'comment' => 'Sách đẹp, nội dung tốt.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.rating', 5)
        ->assertJsonPath('data.comment', 'Sách đẹp, nội dung tốt.')
        ->assertJsonPath('data.status', ReviewStatus::PENDING->value)
        ->assertJsonStructure(['data' => ['id', 'created_at']]);

    $review = Review::query()->where('order_item_id', $item->id)->first();
    expect($review)->not->toBeNull()
        ->and($review->account_id)->toBe($account->id)
        ->and($review->book_id)->toBe($book->id)
        ->and($review->status)->toBe(ReviewStatus::PENDING);

    expect($item->fresh()->is_reviewed)->toBeTrue();
});

test('user cannot review order item belonging to another account', function (): void {
    $owner = Account::factory()->create();
    $other = Account::factory()->create();
    $order = reviewTestOrder($owner);
    $item = reviewTestOrderItem($order, Book::factory()->create());

    $this->actingAs($other, 'web')
        ->postJson("/api/v1/account/order-items/{$item->id}/review", [
            'rating' => 5,
            'comment' => 'Nice',
        ])
        ->assertForbidden();
});

test('user cannot review when order is not completed', function (): void {
    $account = Account::factory()->create();
    $order = reviewTestOrder($account, OrderStatus::CONFIRMED);
    $item = reviewTestOrderItem($order, Book::factory()->create());

    $this->actingAs($account, 'web')
        ->postJson("/api/v1/account/order-items/{$item->id}/review", [
            'rating' => 4,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['order_item']);
});

test('user cannot review already reviewed order item', function (): void {
    $account = Account::factory()->create();
    $order = reviewTestOrder($account);
    $book = Book::factory()->create();
    $item = reviewTestOrderItem($order, $book, true);

    Review::query()->create([
        'account_id' => $account->id,
        'book_id' => $book->id,
        'order_item_id' => $item->id,
        'rating' => 3,
        'comment' => 'Old review',
        'status' => ReviewStatus::PENDING,
    ]);

    $this->actingAs($account, 'web')
        ->postJson("/api/v1/account/order-items/{$item->id}/review", [
            'rating' => 5,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['order_item']);
});

test('user cannot submit duplicate review for same order item', function (): void {
    $account = Account::factory()->create();
    $order = reviewTestOrder($account);
    $item = reviewTestOrderItem($order, Book::factory()->create());

    $payload = ['rating' => 5, 'comment' => 'First'];

    $this->actingAs($account, 'web')
        ->postJson("/api/v1/account/order-items/{$item->id}/review", $payload)
        ->assertCreated();

    $this->actingAs($account, 'web')
        ->postJson("/api/v1/account/order-items/{$item->id}/review", $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['order_item']);
});

test('user can submit half star rating', function (): void {
    $account = Account::factory()->create();
    $order = reviewTestOrder($account);
    $item = reviewTestOrderItem($order, Book::factory()->create());

    $this->actingAs($account, 'web')
        ->postJson("/api/v1/account/order-items/{$item->id}/review", [
            'rating' => 4.5,
            'comment' => 'Sản phẩm tốt',
        ])
        ->assertCreated()
        ->assertJsonPath('data.rating', 4.5);

    expect((float) Review::query()->where('order_item_id', $item->id)->value('rating'))->toBe(4.5);
});

test('submit review rejects invalid rating step', function (): void {
    $account = Account::factory()->create();
    $order = reviewTestOrder($account);
    $item = reviewTestOrderItem($order, Book::factory()->create());

    $this->actingAs($account, 'web')
        ->postJson("/api/v1/account/order-items/{$item->id}/review", [
            'rating' => 4.3,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['rating']);
});

test('guest cannot submit review', function (): void {
    $item = reviewTestOrderItem(
        reviewTestOrder(Account::factory()->create()),
        Book::factory()->create(),
    );

    $this->postJson("/api/v1/account/order-items/{$item->id}/review", [
        'rating' => 5,
    ])->assertUnauthorized();
});
