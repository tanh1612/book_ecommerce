<?php

namespace App\Services\Recommendation;

use App\Models\Book;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecommendationService
{
    public function __construct(
        private RecommendationCacheService $recommendationCacheService,
    ) {}

    /**
     * @return array{strategy: string, books: Collection<int, Book>}
     */
    public function getForYouFeed(?int $limit = null): array
    {
        $displayLimit = max((int) ($limit ?? config('recommendation.display_limit', 10)), 1);

        try {
            $payload = null;
            $defaultStrategy = 'popular';
            $account = Auth::guard('web')->user();
            $isPersonalizedPayload = false;

            if ($account !== null) {
                $userPayload = $this->recommendationCacheService->getUser((int) $account->id);
                if ($userPayload !== null && $userPayload['book_ids'] !== []) {
                    $payload = $userPayload;
                    $defaultStrategy = (string) ($userPayload['strategy'] ?? 'content_based');
                    $isPersonalizedPayload = true;
                }
            }

            if ($payload === null) {
                $payload = $this->recommendationCacheService->getPopular();
            }

            if ($payload === null || $payload['book_ids'] === []) {
                return [
                    'strategy' => $defaultStrategy === 'content_based' ? 'content_based' : 'popular',
                    'books' => collect(),
                ];
            }

            $booksById = $this->fetchEligibleBooksByIds($payload['book_ids']);

            $orderedBooks = [];
            $selectedBookIds = [];
            foreach ($payload['book_ids'] as $bookId) {
                if (! isset($booksById[$bookId])) {
                    continue;
                }

                $orderedBooks[] = $booksById[$bookId];
                $selectedBookIds[(int) $bookId] = true;
                if (count($orderedBooks) >= $displayLimit) {
                    break;
                }
            }

            if ($isPersonalizedPayload && count($orderedBooks) < $displayLimit) {
                $popularPayload = $this->recommendationCacheService->getPopular();

                if ($popularPayload !== null && $popularPayload['book_ids'] !== []) {
                    $popularBooksById = $this->fetchEligibleBooksByIds($popularPayload['book_ids']);

                    foreach ($popularPayload['book_ids'] as $bookId) {
                        $candidateId = (int) $bookId;
                        if (isset($selectedBookIds[$candidateId])) {
                            continue;
                        }

                        if (! isset($popularBooksById[$candidateId])) {
                            continue;
                        }

                        $orderedBooks[] = $popularBooksById[$candidateId];
                        $selectedBookIds[$candidateId] = true;

                        if (count($orderedBooks) >= $displayLimit) {
                            break;
                        }
                    }
                }
            }

            return [
                'strategy' => (string) ($payload['strategy'] ?? 'popular'),
                'books' => collect($orderedBooks),
            ];
        } catch (Throwable $e) {
            Log::warning('Recommendation feed read failed', [
                'limit' => $displayLimit,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return [
                'strategy' => 'popular',
                'books' => collect(),
            ];
        }
    }

    /**
     * @param  array<int, int>  $bookIds
     * @return array<int, Book>
     */
    private function fetchEligibleBooksByIds(array $bookIds): array
    {
        /** @var EloquentCollection<int, Book> $books */
        $books = Book::query()
            ->whereIn('id', $bookIds)
            ->active()
            ->with([
                'images:id,book_id,image_url,sort_order',
                'authors:id,name',
                'inventories:id,book_id,quantity,reserved_quantity',
            ])
            ->get(['id', 'name', 'slug', 'thumbnail', 'original_price', 'selling_price', 'average_rating', 'review_count', 'is_active']);

        $eligible = [];
        foreach ($books as $book) {
            $availableStock = (int) $book->inventories->sum(static function ($inventory): int {
                return max(0, (int) $inventory->quantity - (int) $inventory->reserved_quantity);
            });

            if ($availableStock <= 0) {
                continue;
            }

            $eligible[(int) $book->id] = $book;
        }

        return $eligible;
    }
}
