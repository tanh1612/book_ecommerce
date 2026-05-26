<?php

use App\Enums\Account\AccountRole;
use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentMethod;
use App\Enums\Order\PaymentStatus;
use App\Enums\Review\ReviewStatus;
use App\Filament\Resources\ReviewResource\Pages\ListReviews;
use App\Filament\Resources\ReviewResource\Pages\ViewReview;
use App\Models\Account;
use App\Models\Book;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Review;
use App\Models\ShippingMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function filamentReviewShipping(): ShippingMethod
{
    return ShippingMethod::query()->create([
        'name' => 'Test ship',
        'description' => null,
        'is_active' => true,
    ]);
}

function filamentReviewOrderItem(Account $account, Book $book): OrderItem
{
    $order = Order::query()->create([
        'account_id' => $account->id,
        'shipping_method_id' => filamentReviewShipping()->id,
        'total_amount' => 100000,
        'shipping_fee' => 0,
        'final_amount' => 100000,
        'shipping_name' => 'Test',
        'shipping_phone' => '0900000000',
        'shipping_address' => 'Addr',
        'payment_method' => PaymentMethod::COD,
        'payment_status' => PaymentStatus::PAID,
        'current_status' => OrderStatus::COMPLETED,
    ]);

    return OrderItem::query()->create([
        'order_id' => $order->id,
        'book_id' => $book->id,
        'promotion_id' => null,
        'price' => 100000,
        'quantity' => 1,
        'total_price' => 100000,
        'discount_amount' => 0,
        'is_reviewed' => true,
    ]);
}

function filamentPendingReview(Book $book, float $rating = 5): Review
{
    $account = Account::factory()->create();

    return Review::query()->create([
        'account_id' => $account->id,
        'book_id' => $book->id,
        'order_item_id' => filamentReviewOrderItem($account, $book)->id,
        'rating' => $rating,
        'comment' => 'Test review',
        'status' => ReviewStatus::PENDING,
    ]);
}

test('review list exposes only view table action', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);

    Livewire::actingAs($admin)
        ->test(ListReviews::class)
        ->assertTableActionExists('view')
        ->assertTableActionDoesNotExist('edit')
        ->assertTableActionDoesNotExist('delete');
});

test('review list tabs count and scope approved and rejected reviews', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);
    $book = Book::factory()->create();
    $pending = filamentPendingReview($book);
    $approved = filamentPendingReview($book);
    $rejected = filamentPendingReview($book);

    $approved->update(['status' => ReviewStatus::APPROVED]);
    $rejected->update(['status' => ReviewStatus::REJECTED]);

    $tabs = app(ListReviews::class)->getTabs();

    expect($tabs['all']->getLabel())->toBe('Tổng đánh giá')
        ->and($tabs['all']->getBadge())->toBe(3)
        ->and($tabs['all']->getBadgeColor())->toBe('primary')
        ->and($tabs['approved']->getLabel())->toBe('Đã phê duyệt')
        ->and($tabs['approved']->getBadge())->toBe(1)
        ->and($tabs['approved']->getBadgeColor())->toBe('success')
        ->and($tabs['rejected']->getLabel())->toBe('Đã từ chối')
        ->and($tabs['rejected']->getBadge())->toBe(1)
        ->and($tabs['rejected']->getBadgeColor())->toBe('danger');

    Livewire::actingAs($admin)
        ->test(ListReviews::class)
        ->set('activeTab', 'approved')
        ->assertCanSeeTableRecords([$approved])
        ->assertCanNotSeeTableRecords([$pending, $rejected])
        ->set('activeTab', 'rejected')
        ->assertCanSeeTableRecords([$rejected])
        ->assertCanNotSeeTableRecords([$pending, $approved]);
});

test('view review approve updates status and book aggregates', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);
    $book = Book::factory()->create(['review_count' => 0, 'average_rating' => 0]);
    $review = filamentPendingReview($book, 4.5);

    Livewire::actingAs($admin)
        ->test(ViewReview::class, ['record' => $review->getKey()])
        ->callAction('approve')
        ->assertNotified();

    $review->refresh();
    $book->refresh();

    expect($review->status)->toBe(ReviewStatus::APPROVED)
        ->and($book->review_count)->toBe(1)
        ->and((float) $book->average_rating)->toBe(4.5);
});

test('view review reject updates status without affecting book aggregates', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);
    $book = Book::factory()->create(['review_count' => 0, 'average_rating' => 0]);
    $review = filamentPendingReview($book, 5);

    Livewire::actingAs($admin)
        ->test(ViewReview::class, ['record' => $review->getKey()])
        ->callAction('reject')
        ->assertNotified();

    $review->refresh();
    $book->refresh();

    expect($review->status)->toBe(ReviewStatus::REJECTED)
        ->and($book->review_count)->toBe(0)
        ->and((float) $book->average_rating)->toBe(0.0);
});

test('approve and reject actions are hidden when review is not pending', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);
    $book = Book::factory()->create();
    $review = filamentPendingReview($book);
    $review->update(['status' => ReviewStatus::APPROVED]);

    Livewire::actingAs($admin)
        ->test(ViewReview::class, ['record' => $review->getKey()])
        ->assertActionHidden('approve')
        ->assertActionHidden('reject');
});
