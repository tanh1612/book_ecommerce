<?php

namespace App\Services\Promotion;

use App\Enums\Promotion\PromotionAllocationStatus;
use App\Enums\Promotion\PromotionStatus;
use App\Enums\Promotion\PromotionType;
use App\Models\Book;
use App\Models\Promotion;
use App\Models\PromotionAllocation;
use App\Models\PromotionItem;
use App\Services\Catalog\BookStockAvailabilityService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class FlashSaleResolver
{
    public function __construct(
        private BookStockAvailabilityService $bookStockAvailability,
    ) {}

    public function activeCampaign(?Carbon $at = null): ?Promotion
    {
        $at ??= now();

        $campaigns = Promotion::query()
            ->where('type', PromotionType::FLASH_SALE->value)
            ->whereIn('status', [
                PromotionStatus::SCHEDULED->value,
                PromotionStatus::ACTIVE->value,
            ])
            ->where('start_at', '<=', $at)
            ->where('end_at', '>', $at)
            ->orderBy('id')
            ->get();

        if ($campaigns->count() > 1) {
            Log::critical('Multiple active flash sale campaigns resolved at runtime', [
                'campaign_ids' => $campaigns->pluck('id')->all(),
                'at' => $at->toIso8601String(),
            ]);
        }

        return $campaigns->first();
    }

    public function activeItemForBook(Book $book, int $quantity = 1, ?int $accountId = null, ?Carbon $at = null): ?PromotionItem
    {
        return $this->activeItemsForBooks([(int) $book->id], $accountId, [(int) $book->id => $quantity], $at)
            ->get((int) $book->id);
    }

    /**
     * @param  array<int, int>  $bookIds
     * @param  array<int, int>  $quantityByBookId
     * @return Collection<int, PromotionItem>
     */
    public function activeItemsForBooks(
        array $bookIds,
        ?int $accountId = null,
        array $quantityByBookId = [],
        ?Carbon $at = null,
    ): Collection {
        $bookIds = array_values(array_unique(array_map('intval', $bookIds)));

        if ($bookIds === []) {
            return collect();
        }

        $campaign = $this->activeCampaign($at);

        if ($campaign === null) {
            return collect();
        }

        /** @var Collection<int, PromotionItem> $items */
        $items = PromotionItem::query()
            ->select('promotion_items.*')
            ->with(['promotion', 'book.inventories'])
            ->where('promotion_items.promotion_id', $campaign->id)
            ->whereIn('promotion_items.book_id', $bookIds)
            ->orderByDesc('promotion_items.discount_value')
            ->orderBy('promotion_items.id')
            ->get();

        return $items
            ->filter(function (PromotionItem $item) use ($quantityByBookId, $accountId): bool {
                if (! $this->isItemSellableForDisplay($item)) {
                    return false;
                }

                $bookId = (int) $item->book_id;
                $quantity = max(1, (int) ($quantityByBookId[$bookId] ?? 1));

                return $this->isItemApplicableForQuantity($item, $quantity, $accountId);
            })
            ->keyBy(fn (PromotionItem $item): int => (int) $item->book_id);
    }

    public function isItemDisplayable(PromotionItem $item): bool
    {
        if ($item->stock_limit === null) {
            return true;
        }

        return (int) $item->sold_quantity < (int) $item->stock_limit;
    }

    public function isItemSellableForDisplay(PromotionItem $item): bool
    {
        $item->loadMissing(['book.inventories']);

        $book = $item->book;

        if ($book === null || ! (bool) $book->is_active) {
            return false;
        }

        if (! $this->isItemDisplayable($item)) {
            return false;
        }

        return $this->availableInventoryForBook($book) > 0;
    }

    public function isItemApplicableForQuantity(PromotionItem $item, int $quantity, ?int $accountId = null): bool
    {
        if ($quantity <= 0 || ! $this->isItemSellableForDisplay($item)) {
            return false;
        }

        $inventoryAvailable = $this->availableInventoryForBook($item->book);

        if ($quantity > $inventoryAvailable) {
            return false;
        }

        if ($item->stock_limit !== null) {
            $remaining = (int) $item->stock_limit - (int) $item->sold_quantity;

            if ($quantity > $remaining) {
                return false;
            }
        }

        if ($accountId !== null && $item->max_quantity_per_user !== null) {
            $usedByCustomer = (int) PromotionAllocation::query()
                ->where('promotion_item_id', $item->id)
                ->where('account_id', $accountId)
                ->whereIn('status', [
                    PromotionAllocationStatus::RESERVED->value,
                    PromotionAllocationStatus::CONFIRMED->value,
                ])
                ->sum('quantity');

            if ($usedByCustomer + $quantity > (int) $item->max_quantity_per_user) {
                return false;
            }
        }

        return true;
    }

    public function remainingStock(PromotionItem $item): ?int
    {
        if ($item->stock_limit === null) {
            return null;
        }

        return max(0, (int) $item->stock_limit - (int) $item->sold_quantity);
    }

    public function displayRemainingStock(PromotionItem $item): int
    {
        $item->loadMissing(['book.inventories']);

        $inventoryAvailable = $this->availableInventoryForBook($item->book);
        $flashRemaining = $this->remainingStock($item);

        if ($flashRemaining === null) {
            return $inventoryAvailable;
        }

        return min($flashRemaining, $inventoryAvailable);
    }

    public function availableInventoryForBook(Book $book): int
    {
        if (! (bool) $book->is_active) {
            return 0;
        }

        if ($book->relationLoaded('inventories')) {
            return (int) $book->inventories->sum(
                static fn ($inventory): int => max(0, (int) $inventory->quantity - (int) $inventory->reserved_quantity),
            );
        }

        return $this->bookStockAvailability->getAvailability((int) $book->id)['available_stock'];
    }
}
