<?php

namespace App\Services\Recommendation;

use App\Enums\Order\OrderStatus;
use App\Enums\Recommendation\BookInteractionType;
use App\Enums\Review\ReviewStatus;
use App\Models\Book;
use App\Models\BookInteractionEvent;
use App\Models\Review;
use App\Models\Wishlist;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecommendationCandidateService
{
    /**
     * @return array<int, int>
     */
    public function buildPopularCandidateBookIds(?int $limit = null): array
    {
        $candidateLimit = max((int) ($limit ?? config('recommendation.candidate_limit', 50)), 1);
        $salesWindowDays = max((int) config('recommendation.popular_sales_window_days', 90), 1);
        $recencyWindowDays = max((int) config('recommendation.popular_recency_window_days', 60), 1);
        $weights = (array) config('recommendation.popular_weights', []);

        $salesWeight = (float) ($weights['sales'] ?? 5);
        $ratingWeight = (float) ($weights['rating'] ?? 3);
        $reviewCountWeight = (float) ($weights['review_count'] ?? 1);
        $recencyWeight = (float) ($weights['recency'] ?? 1);

        try {
            $salesSince = now()->subDays($salesWindowDays);

            $books = Book::query()
                ->active()
                ->select([
                    'books.id',
                    'books.average_rating',
                    'books.review_count',
                    'books.created_at',
                ])
                ->selectSub(function ($query) use ($salesSince): void {
                    $query
                        ->from('order_items')
                        ->join('orders', 'orders.id', '=', 'order_items.order_id')
                        ->whereColumn('order_items.book_id', 'books.id')
                        ->where('orders.current_status', OrderStatus::COMPLETED->value)
                        ->where('orders.created_at', '>=', $salesSince)
                        ->selectRaw('COALESCE(SUM(order_items.quantity), 0)');
                }, 'sold_quantity')
                ->get();

            return $books
                ->map(function (Book $book) use ($salesWeight, $ratingWeight, $reviewCountWeight, $recencyWeight, $recencyWindowDays): array {
                    $daysSinceCreated = now()->diffInDays($book->created_at ?? now());
                    $recencyScore = max(0, $recencyWindowDays - $daysSinceCreated);
                    $score = ((int) ($book->sold_quantity ?? 0) * $salesWeight)
                        + (((float) $book->average_rating) * $ratingWeight)
                        + (log10(((int) $book->review_count) + 1) * $reviewCountWeight)
                        + ($recencyScore * $recencyWeight);

                    return [
                        'id' => (int) $book->id,
                        'score' => $score,
                        'sold_quantity' => (int) ($book->sold_quantity ?? 0),
                        'average_rating' => (float) $book->average_rating,
                        'review_count' => (int) $book->review_count,
                        'created_at' => $book->created_at,
                    ];
                })
                ->sort(function (array $a, array $b): int {
                    return [$b['score'], $b['sold_quantity'], $b['average_rating'], $b['review_count'], $b['created_at']?->timestamp ?? 0, $b['id']]
                        <=> [$a['score'], $a['sold_quantity'], $a['average_rating'], $a['review_count'], $a['created_at']?->timestamp ?? 0, $a['id']];
                })
                ->take($candidateLimit)
                ->pluck('id')
                ->values()
                ->all();
        } catch (Throwable $e) {
            Log::error('Build popular recommendation candidates failed', [
                'candidate_limit' => $candidateLimit,
                'sales_window_days' => $salesWindowDays,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            throw $e;
        }
    }

    /**
     * @return array<int, int>
     */
    public function buildUserCandidateBookIds(int $accountId, ?int $limit = null): array
    {
        $candidateLimit = max((int) ($limit ?? config('recommendation.candidate_limit', 50)), 1);
        $minimumDistinctBooks = max((int) config('recommendation.minimum_distinct_books', 5), 1);
        $weights = (array) config('recommendation.weights', []);
        $windows = (array) config('recommendation.signal_windows_days', []);
        $recentPurchaseExclusionDays = max((int) config('recommendation.recent_purchase_exclusion_days', 90), 1);
        $contentBased = (array) config('recommendation.content_based', []);
        $categoryWeight = (float) ($contentBased['category_weight'] ?? 1.5);
        $authorWeight = (float) ($contentBased['author_weight'] ?? 2.0);
        $popularityBlendWeight = (float) ($contentBased['popularity_blend_weight'] ?? 0.1);

        try {
            $viewWeight = (float) ($weights['view'] ?? 1);
            $cartAddWeight = (float) ($weights['cart_add'] ?? 3);
            $wishlistWeight = (float) ($weights['wishlist'] ?? 4);
            $purchaseWeight = (float) ($weights['purchase'] ?? 5);
            $positiveRatingWeight = (float) ($weights['positive_rating'] ?? 5);
            $negativeRatingWeight = (float) ($weights['negative_rating'] ?? -3);

            $viewSince = now()->subDays(max((int) ($windows['view'] ?? 90), 1));
            $cartAddSince = now()->subDays(max((int) ($windows['cart_add'] ?? 90), 1));
            $purchaseSince = now()->subDays(max((int) ($windows['purchase'] ?? 365), 1));
            $recentPurchaseSince = now()->subDays($recentPurchaseExclusionDays);

            $seedScores = [];
            $strongSignalBookIds = [];

            $viewEvents = BookInteractionEvent::query()
                ->where('account_id', $accountId)
                ->where('event_type', BookInteractionType::View)
                ->where('created_at', '>=', $viewSince)
                ->selectRaw('book_id, COUNT(*) as aggregate_count')
                ->groupBy('book_id')
                ->get();

            foreach ($viewEvents as $event) {
                $bookId = (int) $event->book_id;
                $seedScores[$bookId] = ($seedScores[$bookId] ?? 0) + ((int) $event->aggregate_count * $viewWeight);
            }

            $cartAddEvents = BookInteractionEvent::query()
                ->where('account_id', $accountId)
                ->where('event_type', BookInteractionType::CartAdd)
                ->where('created_at', '>=', $cartAddSince)
                ->selectRaw('book_id, COUNT(*) as aggregate_count')
                ->groupBy('book_id')
                ->get();

            foreach ($cartAddEvents as $event) {
                $bookId = (int) $event->book_id;
                $seedScores[$bookId] = ($seedScores[$bookId] ?? 0) + ((int) $event->aggregate_count * $cartAddWeight);
            }

            $wishlistBookIds = Wishlist::query()
                ->where('account_id', $accountId)
                ->pluck('book_id');

            foreach ($wishlistBookIds as $bookId) {
                $id = (int) $bookId;
                $seedScores[$id] = ($seedScores[$id] ?? 0) + $wishlistWeight;
                $strongSignalBookIds[$id] = true;
            }

            $completedPurchases = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.account_id', $accountId)
                ->where('orders.current_status', OrderStatus::COMPLETED->value)
                ->where('orders.created_at', '>=', $purchaseSince)
                ->selectRaw('order_items.book_id, SUM(order_items.quantity) as aggregate_count')
                ->groupBy('order_items.book_id')
                ->get();

            foreach ($completedPurchases as $purchase) {
                $bookId = (int) $purchase->book_id;
                $seedScores[$bookId] = ($seedScores[$bookId] ?? 0) + ((int) $purchase->aggregate_count * $purchaseWeight);
                $strongSignalBookIds[$bookId] = true;
            }

            $approvedReviews = Review::query()
                ->where('account_id', $accountId)
                ->where('status', ReviewStatus::APPROVED)
                ->select(['book_id', 'rating'])
                ->get();

            foreach ($approvedReviews as $review) {
                $bookId = (int) $review->book_id;
                $rating = (float) $review->rating;
                if ($rating >= 4.0) {
                    $seedScores[$bookId] = ($seedScores[$bookId] ?? 0) + $positiveRatingWeight;
                    $strongSignalBookIds[$bookId] = true;
                    continue;
                }

                if ($rating <= 2.0) {
                    $seedScores[$bookId] = ($seedScores[$bookId] ?? 0) + $negativeRatingWeight;
                    $strongSignalBookIds[$bookId] = true;
                }
            }

            if (count($seedScores) < $minimumDistinctBooks || count($strongSignalBookIds) === 0) {
                return [];
            }

            $positiveSeedBookIds = array_values(array_map('intval', array_keys(array_filter($seedScores, fn ($score): bool => $score > 0))));
            if ($positiveSeedBookIds === []) {
                return [];
            }

            $categoryAffinities = [];
            $seedCategories = DB::table('book_categories')
                ->whereIn('book_id', $positiveSeedBookIds)
                ->select(['book_id', 'category_id'])
                ->get();

            foreach ($seedCategories as $row) {
                $seedBookId = (int) $row->book_id;
                $categoryId = (int) $row->category_id;
                $categoryAffinities[$categoryId] = ($categoryAffinities[$categoryId] ?? 0) + ($seedScores[$seedBookId] ?? 0);
            }

            $authorAffinities = [];
            $seedAuthors = DB::table('book_authors')
                ->whereIn('book_id', $positiveSeedBookIds)
                ->select(['book_id', 'author_id'])
                ->get();

            foreach ($seedAuthors as $row) {
                $seedBookId = (int) $row->book_id;
                $authorId = (int) $row->author_id;
                $authorAffinities[$authorId] = ($authorAffinities[$authorId] ?? 0) + ($seedScores[$seedBookId] ?? 0);
            }

            $excludedBookIds = array_fill_keys(array_map('intval', array_keys($seedScores)), true);
            $recentPurchaseBookIds = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.account_id', $accountId)
                ->where('orders.current_status', OrderStatus::COMPLETED->value)
                ->where('orders.created_at', '>=', $recentPurchaseSince)
                ->pluck('order_items.book_id');

            foreach ($recentPurchaseBookIds as $bookId) {
                $excludedBookIds[(int) $bookId] = true;
            }

            $candidateBooks = Book::query()
                ->active()
                ->whereNotIn('id', array_keys($excludedBookIds))
                ->with([
                    'categories:id',
                    'authors:id',
                ])
                ->get(['id', 'average_rating', 'review_count', 'created_at']);

            $ranked = $candidateBooks->map(function (Book $book) use ($categoryAffinities, $authorAffinities, $categoryWeight, $authorWeight, $popularityBlendWeight): array {
                $categoryScore = 0.0;
                foreach ($book->categories as $category) {
                    $categoryScore += (float) ($categoryAffinities[(int) $category->id] ?? 0);
                }

                $authorScore = 0.0;
                foreach ($book->authors as $author) {
                    $authorScore += (float) ($authorAffinities[(int) $author->id] ?? 0);
                }

                $score = ($categoryScore * $categoryWeight) + ($authorScore * $authorWeight);
                $score += $this->popularScoreForBook($book) * $popularityBlendWeight;

                return [
                    'id' => (int) $book->id,
                    'score' => $score,
                ];
            })
                ->filter(fn (array $row): bool => $row['score'] > 0)
                ->sort(fn (array $a, array $b): int => [$b['score'], $b['id']] <=> [$a['score'], $a['id']])
                ->take($candidateLimit)
                ->pluck('id')
                ->values()
                ->all();

            return $ranked;
        } catch (Throwable $e) {
            Log::error('Build user recommendation candidates failed', [
                'account_id' => $accountId,
                'candidate_limit' => $candidateLimit,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            throw $e;
        }
    }

    private function popularScoreForBook(Book $book): float
    {
        $popularWeights = (array) config('recommendation.popular_weights', []);
        $recencyWindowDays = max((int) config('recommendation.popular_recency_window_days', 60), 1);
        $ratingWeight = (float) ($popularWeights['rating'] ?? 3);
        $reviewCountWeight = (float) ($popularWeights['review_count'] ?? 1);
        $recencyWeight = (float) ($popularWeights['recency'] ?? 1);

        $daysSinceCreated = now()->diffInDays($book->created_at ?? now());
        $recencyScore = max(0, $recencyWindowDays - $daysSinceCreated);

        return (((float) $book->average_rating) * $ratingWeight)
            + (log10(((int) $book->review_count) + 1) * $reviewCountWeight)
            + ($recencyScore * $recencyWeight);
    }
}
