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
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function bookReviewsTestShipping(): ShippingMethod
{
    return ShippingMethod::query()->create([
        'name' => 'Test ship',
        'description' => null,
        'is_active' => true,
    ]);
}

function bookReviewsTestOrderItem(Account $account, Book $book): OrderItem
{
    $order = Order::query()->create([
        'account_id' => $account->id,
        'shipping_method_id' => bookReviewsTestShipping()->id,
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

function bookReviewsTestReview(
    Book $book,
    Account $account,
    ReviewStatus $status,
    float $rating,
    ?string $createdAt = null,
    ?string $comment = null,
): Review {
    $item = bookReviewsTestOrderItem($account, $book);

    $review = Review::query()->create([
        'account_id' => $account->id,
        'book_id' => $book->id,
        'order_item_id' => $item->id,
        'rating' => $rating,
        'comment' => $comment ?? "Review {$rating}",
        'status' => $status,
    ]);

    if ($createdAt !== null) {
        $review->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();
    }

    return $review->fresh();
}

test('guest can list approved reviews for a book', function (): void {
    $book = Book::factory()->create();
    $account = Account::factory()->create();
    UserProfile::query()->create([
        'account_id' => $account->id,
        'first_name' => 'Nguyen',
        'last_name' => 'Van A',
        'phone' => null,
        'gender' => null,
        'birthday' => null,
    ]);

    $review = bookReviewsTestReview($book, $account, ReviewStatus::APPROVED, 4.5);

    $this->getJson("/api/v1/books/{$book->slug}/reviews")
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                [
                    'id',
                    'rating',
                    'comment',
                    'created_at',
                    'reviewer_name',
                ],
            ],
            'links',
            'meta',
        ])
        ->assertJsonPath('data.0.id', $review->id)
        ->assertJsonPath('data.0.rating', 4.5)
        ->assertJsonPath('data.0.reviewer_name', 'Nguyen Van A')
        ->assertJsonMissingPath('data.0.admin_reply')
        ->assertJsonMissingPath('data.0.status')
        ->assertJsonMissingPath('data.0.account_id');
});

test('pending and rejected reviews are not listed', function (): void {
    $book = Book::factory()->create();
    $account = Account::factory()->create();
    $approved = bookReviewsTestReview($book, $account, ReviewStatus::APPROVED, 5);
    bookReviewsTestReview($book, $account, ReviewStatus::PENDING, 4);
    bookReviewsTestReview($book, $account, ReviewStatus::REJECTED, 3);

    $this->getJson("/api/v1/books/{$book->slug}/reviews")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $approved->id);
});

test('reviews from other books are not listed', function (): void {
    $book = Book::factory()->create();
    $otherBook = Book::factory()->create();
    $account = Account::factory()->create();
    $review = bookReviewsTestReview($book, $account, ReviewStatus::APPROVED, 5);
    bookReviewsTestReview($otherBook, $account, ReviewStatus::APPROVED, 4);

    $this->getJson("/api/v1/books/{$book->slug}/reviews")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $review->id);
});

test('reviews are ordered by rating desc then created at desc then id desc', function (): void {
    $book = Book::factory()->create();
    $account = Account::factory()->create();

    $lowOlder = bookReviewsTestReview($book, $account, ReviewStatus::APPROVED, 3, '2026-01-01 10:00:00');
    $highOlder = bookReviewsTestReview($book, $account, ReviewStatus::APPROVED, 5, '2026-01-01 10:00:00');
    $highNewer = bookReviewsTestReview($book, $account, ReviewStatus::APPROVED, 5, '2026-02-01 10:00:00');

    $response = $this->getJson("/api/v1/books/{$book->slug}/reviews")
        ->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toBe([
        $highNewer->id,
        $highOlder->id,
        $lowOlder->id,
    ]);
});

test('book reviews pagination works', function (): void {
    $book = Book::factory()->create();
    $account = Account::factory()->create();

    foreach (range(1, 10) as $index) {
        bookReviewsTestReview(
            $book,
            $account,
            ReviewStatus::APPROVED,
            5 - ($index % 5) * 0.5,
        );
    }

    $response = $this->getJson("/api/v1/books/{$book->slug}/reviews?per_page=3&page=2")
        ->assertOk()
        ->assertJsonPath('meta.per_page', 3)
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonCount(3, 'data');

    $nextLink = $response->json('links.next');
    expect($nextLink)->not->toBeNull()
        ->and($nextLink)->toContain('per_page=3');
});

test('unknown book slug returns not found', function (): void {
    $this->getJson('/api/v1/books/non-existent-slug/reviews')
        ->assertNotFound();
});
