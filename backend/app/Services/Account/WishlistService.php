<?php

namespace App\Services\Account;

use App\Models\Account;
use App\Models\Book;
use App\Models\Wishlist;
use App\Services\Recommendation\RecommendationRefreshService;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class WishlistService
{
    public function __construct(
        private RecommendationRefreshService $recommendationRefreshService,
    ) {}

    /**
     * @var list<string>
     */
    private const BOOK_EAGER_LOADS = [
        'authors:id,name',
        'images:id,book_id,image_url,sort_order',
        'inventories:id,book_id,quantity,reserved_quantity',
    ];

    /**
     * @return Collection<int, Book>
     */
    public function list(Account $account): Collection
    {
        $items = Wishlist::query()
            ->where('account_id', $account->id)
            ->with(['book' => fn ($query) => $query->with(self::BOOK_EAGER_LOADS)])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return $items
            ->map(fn (Wishlist $wishlist): ?Book => $wishlist->book)
            ->filter(fn (?Book $book): bool => $book !== null)
            ->values();
    }

    public function add(Account $account, int $bookId): Book
    {
        $book = Book::query()
            ->whereKey($bookId)
            ->firstOrFail();

        try {
            Wishlist::query()->firstOrCreate([
                'account_id' => $account->id,
                'book_id' => $book->id,
            ]);
        } catch (QueryException $e) {
            if (! $this->isUniqueConstraintViolation($e)) {
                Log::error('Wishlist add failed', [
                    'account_id' => $account->id,
                    'book_id' => $bookId,
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }
        } catch (Throwable $e) {
            Log::error('Wishlist add failed', [
                'account_id' => $account->id,
                'book_id' => $bookId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $this->registerRecommendationRefreshAfterCommit((int) $account->id, 'wishlist_add');

        return $this->loadBookForResource($book);
    }

    public function remove(Account $account, Book $book): void
    {
        $wishlist = Wishlist::query()
            ->where('account_id', $account->id)
            ->where('book_id', $book->id)
            ->firstOrFail();

        try {
            $wishlist->delete();
        } catch (Throwable $e) {
            Log::error('Wishlist remove failed', [
                'account_id' => $account->id,
                'book_id' => $book->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $this->registerRecommendationRefreshAfterCommit((int) $account->id, 'wishlist_remove');
    }

    private function loadBookForResource(Book $book): Book
    {
        return Book::query()
            ->whereKey($book->id)
            ->with(self::BOOK_EAGER_LOADS)
            ->firstOrFail();
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;

        return in_array($sqlState, ['23000', '23505'], true);
    }

    private function registerRecommendationRefreshAfterCommit(int $accountId, string $reason): void
    {
        try {
            DB::afterCommit(function () use ($accountId, $reason): void {
                $this->recommendationRefreshService->refreshUserRecommendations($accountId, $reason);
            });
        } catch (Throwable $e) {
            Log::warning('Register recommendation refresh after commit failed for wishlist', [
                'account_id' => $accountId,
                'reason' => $reason,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }
    }
}
