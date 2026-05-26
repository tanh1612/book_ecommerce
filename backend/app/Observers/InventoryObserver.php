<?php

namespace App\Observers;

use App\Enums\Inventory\InventoryStockAlertType;
use App\Jobs\Inventory\NotifyInventoryStockStatusChangedJob;
use App\Models\Book;
use App\Models\Inventory;
use App\Services\Catalog\CatalogCacheService;
use App\Services\Search\BookMeilisearchSyncDispatcher;
use Illuminate\Support\Facades\Log;
use Throwable;

class InventoryObserver
{
    public function __construct(
        private CatalogCacheService $catalogCache,
        private BookMeilisearchSyncDispatcher $meilisearchSync,
    ) {}

    public function created(Inventory $inventory): void
    {
        $this->onInventoryChanged($inventory);
        $this->dispatchImmediateStockAlertIfNeeded($inventory, isNew: true);
    }

    public function updated(Inventory $inventory): void
    {
        $this->onInventoryChanged($inventory);
        $this->dispatchImmediateStockAlertIfNeeded($inventory);
    }

    public function deleted(Inventory $inventory): void
    {
        $this->onInventoryChanged($inventory);
    }

    private function onInventoryChanged(Inventory $inventory): void
    {
        $bookId = (int) $inventory->book_id;
        $this->catalogCache->forgetBookStock($bookId);
        $this->meilisearchSync->dispatch($bookId);
        $this->syncBookInactiveWhenOutOfStock($bookId);
    }

    private function dispatchImmediateStockAlertIfNeeded(Inventory $inventory, bool $isNew = false): void
    {
        if (! config('inventory.low_stock_immediate_notifications', true)) {
            return;
        }

        $threshold = (int) config('inventory.low_stock_threshold', 5);

        $oldAvailable = $isNew
            ? 0
            : $this->availableStockFromQuantities(
                (int) $inventory->getOriginal('quantity'),
                (int) $inventory->getOriginal('reserved_quantity'),
            );

        $newAvailable = $this->availableStockFromQuantities(
            (int) $inventory->quantity,
            (int) $inventory->reserved_quantity,
        );

        $wasLowStock = $oldAvailable > 0 && $oldAvailable <= $threshold;
        $isLowStock = $newAvailable > 0 && $newAvailable <= $threshold;

        $wasOutOfStock = $oldAvailable <= 0;
        $isOutOfStock = $newAvailable <= 0;

        if (! $wasOutOfStock && $isOutOfStock) {
            NotifyInventoryStockStatusChangedJob::dispatch(
                (int) $inventory->id,
                InventoryStockAlertType::OutOfStock,
            )->afterCommit();

            return;
        }

        if (! $wasLowStock && $isLowStock) {
            NotifyInventoryStockStatusChangedJob::dispatch(
                (int) $inventory->id,
                InventoryStockAlertType::LowStock,
            )->afterCommit();
        }
    }

    private function availableStockFromQuantities(int $quantity, int $reserved): int
    {
        return max(0, $quantity - $reserved);
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
