<?php

namespace App\Observers;

use App\Enums\Review\ReviewStatus;
use App\Models\Book;
use App\Models\Review;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReviewObserver
{
    public function saved(Review $review): void
    {
        $this->recalculateForBookId((int) $review->book_id);

        if ($review->wasChanged('book_id')) {
            $previousBookId = $review->getOriginal('book_id');
            if ($previousBookId !== null && (int) $previousBookId !== (int) $review->book_id) {
                $this->recalculateForBookId((int) $previousBookId);
            }
        }
    }

    public function deleted(Review $review): void
    {
        $this->recalculateForBookId((int) $review->book_id);
    }

    private function recalculateForBookId(int $bookId): void
    {
        if ($bookId <= 0) {
            return;
        }

        try {
            DB::transaction(function () use ($bookId): void {
                $stats = Review::query()
                    ->where('book_id', $bookId)
                    ->where('status', ReviewStatus::APPROVED)
                    ->selectRaw('COUNT(*) AS cnt, COALESCE(AVG(rating), 0) AS avg_rating')
                    ->first();

                Book::query()->where('id', $bookId)->update([
                    'review_count' => (int) $stats->cnt,
                    'average_rating' => round((float) $stats->avg_rating, 2),
                ]);
            });
        } catch (Throwable $e) {
            Log::error('Failed to recalculate book rating aggregates', [
                'book_id' => $bookId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
