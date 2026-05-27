<?php

namespace App\Observers;

use App\Enums\Review\ReviewStatus;
use App\Models\Book;
use App\Models\Review;
use App\Services\Catalog\CatalogCacheService;
use App\Services\Search\BookMeilisearchSyncDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReviewObserver
{
    public function __construct(
        private CatalogCacheService $catalogCache,
        private BookMeilisearchSyncDispatcher $meilisearchSync,
    ) {}

    public function saved(Review $review): void
    {
        $previousBookId = $review->wasChanged('book_id')
            ? $review->getOriginal('book_id')
            : null;

        $this->recalculateForBookId((int) $review->book_id);

        if ($previousBookId !== null && (int) $previousBookId !== (int) $review->book_id) {
            $this->recalculateForBookId((int) $previousBookId);
        }

        $this->meilisearchSync->dispatch((int) $review->book_id);

        if ($previousBookId !== null && (int) $previousBookId !== (int) $review->book_id) {
            $this->meilisearchSync->dispatch((int) $previousBookId);
        }
    }

    public function deleted(Review $review): void
    {
        $this->recalculateForBookId((int) $review->book_id);
        $this->meilisearchSync->dispatch((int) $review->book_id);
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

            $this->catalogCache->forgetBookByIdAfterCommit($bookId);
        } catch (Throwable $e) {
            Log::error('Failed to recalculate book rating aggregates', [
                'book_id' => $bookId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
