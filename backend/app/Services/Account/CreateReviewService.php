<?php

namespace App\Services\Account;

use App\Enums\Order\OrderStatus;
use App\Enums\Review\ReviewStatus;
use App\Models\Account;
use App\Models\Book;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Review;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateReviewService
{
    /**
     * @return array{can_review: bool, review_target_id: int|null}
     */
    public function reviewEligibilityForBook(Account $account, Book $book): array
    {
        $orderItem = $this->findReviewableOrderItemForBook($account, $book);

        if ($orderItem === null) {
            return [
                'can_review' => false,
                'review_target_id' => null,
            ];
        }

        $orderItem->loadMissing(['order', 'book']);

        if ($orderItem->order === null || ! $this->canReviewOrderItem($orderItem->order, $orderItem)) {
            return [
                'can_review' => false,
                'review_target_id' => null,
            ];
        }

        return [
            'can_review' => true,
            'review_target_id' => $orderItem->id,
        ];
    }

    public function findReviewableOrderItemForBook(Account $account, Book $book): ?OrderItem
    {
        if (! Book::query()->whereKey($book->id)->exists()) {
            return null;
        }

        return OrderItem::query()
            ->select('order_items.*')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.account_id', $account->id)
            ->where('orders.current_status', OrderStatus::COMPLETED)
            ->where('order_items.book_id', $book->id)
            ->where('order_items.is_reviewed', false)
            ->orderByDesc('orders.created_at')
            ->orderByDesc('order_items.id')
            ->first();
    }

    public function canReviewOrderItem(Order $order, OrderItem $item): bool
    {
        if ($order->current_status !== OrderStatus::COMPLETED) {
            return false;
        }

        if ($item->is_reviewed) {
            return false;
        }

        if ($item->book_id === null) {
            return false;
        }

        if ($item->relationLoaded('book')) {
            return $item->book !== null;
        }

        return Book::query()->whereKey($item->book_id)->exists();
    }

    /**
     * @param  array{rating: float|int, comment?: string|null}  $data
     */
    public function create(Account $account, OrderItem $orderItem, array $data): Review
    {
        try {
            return DB::transaction(function () use ($account, $orderItem, $data): Review {
                /** @var OrderItem|null $locked */
                $locked = OrderItem::query()
                    ->whereKey($orderItem->id)
                    ->lockForUpdate()
                    ->first();

                if ($locked === null) {
                    throw ValidationException::withMessages([
                        'order_item' => ['Dòng đơn hàng không tồn tại.'],
                    ]);
                }

                $locked->loadMissing(['order', 'book', 'review']);

                if ($locked->order === null || $locked->order->account_id !== $account->id) {
                    throw ValidationException::withMessages([
                        'order_item' => ['Bạn không có quyền đánh giá sản phẩm này.'],
                    ]);
                }

                if ($locked->order->current_status !== OrderStatus::COMPLETED) {
                    throw ValidationException::withMessages([
                        'order_item' => ['Chỉ được đánh giá khi đơn hàng đã hoàn tất.'],
                    ]);
                }

                if ($locked->is_reviewed) {
                    throw ValidationException::withMessages([
                        'order_item' => ['Sản phẩm này đã được đánh giá.'],
                    ]);
                }

                if ($locked->review !== null) {
                    throw ValidationException::withMessages([
                        'order_item' => ['Đã tồn tại đánh giá cho sản phẩm này.'],
                    ]);
                }

                if ($locked->book_id === null || $locked->book === null) {
                    throw ValidationException::withMessages([
                        'order_item' => ['Sách không còn tồn tại, không thể đánh giá.'],
                    ]);
                }

                try {
                    $review = Review::query()->create([
                        'account_id' => $account->id,
                        'book_id' => $locked->book_id,
                        'order_item_id' => $locked->id,
                        'rating' => $data['rating'],
                        'comment' => $data['comment'] ?? null,
                        'status' => ReviewStatus::PENDING,
                    ]);
                } catch (QueryException $e) {
                    if ($this->isDuplicateOrderItemReview($e)) {
                        throw ValidationException::withMessages([
                            'order_item' => ['Đã tồn tại đánh giá cho sản phẩm này.'],
                        ]);
                    }

                    throw $e;
                }

                $locked->update(['is_reviewed' => true]);

                return $review;
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Create review failed', [
                'account_id' => $account->id,
                'order_item_id' => $orderItem->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function isDuplicateOrderItemReview(QueryException $e): bool
    {
        $errorCode = (int) ($e->errorInfo[1] ?? 0);

        return $errorCode === 1062;
    }
}
