<?php

namespace App\Observers;

use App\Models\Book;
use App\Models\Inventory;
use App\Services\Catalog\CatalogCacheService;
use Illuminate\Support\Facades\Log;
use Throwable;

class InventoryObserver
{
    public function __construct(
        private CatalogCacheService $catalogCache,
    ) {}

    public function saved(Inventory $inventory): void
    {
        $bookId = (int) $inventory->book_id;
        $this->catalogCache->forgetBookById($bookId);
        $this->syncBookInactiveWhenOutOfStock($bookId);
    }

    public function deleted(Inventory $inventory): void
    {
        $bookId = (int) $inventory->book_id;
        $this->catalogCache->forgetBookById($bookId);
        $this->syncBookInactiveWhenOutOfStock($bookId);
    }

    /**
     * When every warehouse row for the book has no sellable quantity, mark the book inactive for storefront rules.
     */
    private function syncBookInactiveWhenOutOfStock(int $bookId): void
    {
        if ($bookId <= 0) {
            return;
        }

        try {
            $aggregate = Inventory::query()
                ->where('book_id', $bookId)
                ->selectRaw('COUNT(*) as inventory_rows, COALESCE(SUM(GREATEST(quantity - reserved_quantity, 0)), 0) as available')
                ->first();

            if ($aggregate === null || (int) $aggregate->inventory_rows === 0) {
                return;
            }

            if ((int) $aggregate->available > 0) {
                return;
            }

            $book = Book::query()->whereKey($bookId)->first(['id', 'is_active']);
            if ($book === null || ! $book->is_active) {
                return;
            }

            $book->update(['is_active' => false]);
        } catch (Throwable $e) {
            Log::error('Book is_active sync from inventory failed', [
                'book_id' => $bookId,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }
    }
}
