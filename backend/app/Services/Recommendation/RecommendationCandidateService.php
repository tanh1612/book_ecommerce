<?php

namespace App\Services\Recommendation;

use App\Enums\Order\OrderStatus;
use App\Models\Book;
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
}
