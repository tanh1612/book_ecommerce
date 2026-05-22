<?php

use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentMethod;
use App\Enums\Order\PaymentStatus;
use App\Models\Account;
use App\Models\Book;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ShippingMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function eligibilityTestShipping(): ShippingMethod
{
    return ShippingMethod::query()->create([
        'name' => 'Test ship',
        'description' => null,
        'is_active' => true,
    ]);
}

function eligibilityTestOrder(Account $account, OrderStatus $status = OrderStatus::COMPLETED): Order
{
    return Order::query()->create([
        'account_id' => $account->id,
        'shipping_method_id' => eligibilityTestShipping()->id,
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

function eligibilityTestOrderItem(Order $order, Book $book, bool $isReviewed = false): OrderItem
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

test('guest cannot check book review eligibility', function (): void {
    $book = Book::factory()->create();

    $this->getJson("/api/v1/books/{$book->slug}/review-eligibility")
        ->assertUnauthorized();
});

test('user who never purchased book receives cannot review', function (): void {
    $account = Account::factory()->create();
    $book = Book::factory()->create();

    $this->actingAs($account, 'sanctum')
        ->getJson("/api/v1/books/{$book->slug}/review-eligibility")
        ->assertOk()
        ->assertJsonPath('data.can_review', false)
        ->assertJsonPath('data.review_target_id', null);
});

test('user with completed unreviewed order item can review book', function (): void {
    $account = Account::factory()->create();
    $book = Book::factory()->create();
    $order = eligibilityTestOrder($account);
    $item = eligibilityTestOrderItem($order, $book);

    $this->actingAs($account, 'sanctum')
        ->getJson("/api/v1/books/{$book->slug}/review-eligibility")
        ->assertOk()
        ->assertJsonPath('data.can_review', true)
        ->assertJsonPath('data.review_target_id', $item->id);
});

test('user with non completed order cannot review', function (): void {
    $account = Account::factory()->create();
    $book = Book::factory()->create();
    $order = eligibilityTestOrder($account, OrderStatus::CONFIRMED);
    eligibilityTestOrderItem($order, $book);

    $this->actingAs($account, 'sanctum')
        ->getJson("/api/v1/books/{$book->slug}/review-eligibility")
        ->assertOk()
        ->assertJsonPath('data.can_review', false)
        ->assertJsonPath('data.review_target_id', null);
});

test('user with already reviewed order item cannot review', function (): void {
    $account = Account::factory()->create();
    $book = Book::factory()->create();
    $order = eligibilityTestOrder($account);
    eligibilityTestOrderItem($order, $book, true);

    $this->actingAs($account, 'sanctum')
        ->getJson("/api/v1/books/{$book->slug}/review-eligibility")
        ->assertOk()
        ->assertJsonPath('data.can_review', false)
        ->assertJsonPath('data.review_target_id', null);
});

test('returns newest eligible order item when multiple exist for same book', function (): void {
    $account = Account::factory()->create();
    $book = Book::factory()->create();

    $olderOrder = eligibilityTestOrder($account);
    $olderOrder->forceFill(['created_at' => now()->subDays(2)])->save();
    eligibilityTestOrderItem($olderOrder, $book);

    $newerOrder = eligibilityTestOrder($account);
    $newerOrder->forceFill(['created_at' => now()->subDay()])->save();
    $newerItem = eligibilityTestOrderItem($newerOrder, $book);

    $this->actingAs($account, 'sanctum')
        ->getJson("/api/v1/books/{$book->slug}/review-eligibility")
        ->assertOk()
        ->assertJsonPath('data.can_review', true)
        ->assertJsonPath('data.review_target_id', $newerItem->id);
});

test('user does not receive another accounts review target id', function (): void {
    $owner = Account::factory()->create();
    $other = Account::factory()->create();
    $book = Book::factory()->create();
    $order = eligibilityTestOrder($owner);
    eligibilityTestOrderItem($order, $book);

    $this->actingAs($other, 'sanctum')
        ->getJson("/api/v1/books/{$book->slug}/review-eligibility")
        ->assertOk()
        ->assertJsonPath('data.can_review', false)
        ->assertJsonPath('data.review_target_id', null);
});

test('eligibility becomes false after review is submitted', function (): void {
    $account = Account::factory()->create();
    $book = Book::factory()->create();
    $order = eligibilityTestOrder($account);
    $item = eligibilityTestOrderItem($order, $book);

    $this->actingAs($account, 'sanctum')
        ->getJson("/api/v1/books/{$book->slug}/review-eligibility")
        ->assertJsonPath('data.can_review', true);

    $this->actingAs($account, 'sanctum')
        ->postJson("/api/v1/account/order-items/{$item->id}/review", [
            'rating' => 4.5,
            'comment' => 'Hay',
        ])
        ->assertCreated();

    $this->actingAs($account, 'sanctum')
        ->getJson("/api/v1/books/{$book->slug}/review-eligibility")
        ->assertOk()
        ->assertJsonPath('data.can_review', false)
        ->assertJsonPath('data.review_target_id', null);
});

test('unknown book slug returns not found', function (): void {
    $account = Account::factory()->create();

    $this->actingAs($account, 'sanctum')
        ->getJson('/api/v1/books/non-existent-slug/review-eligibility')
        ->assertNotFound();
});
