<?php

namespace App\Services\Promotion;

use App\Enums\Promotion\PromotionStatus;
use App\Models\Book;
use App\Models\PromotionItem;
use Illuminate\Support\Collection;

class PromotionResolver
{
    public function activeItemForLoadedBook(Book $book): ?PromotionItem
    {
        $now = now();

        return $book->promotionItems
            ->filter(function (PromotionItem $item) use ($now): bool {
                $promotion = $item->promotion;

                return $promotion !== null
                    && $promotion->status === PromotionStatus::ACTIVE
                    && $promotion->start_at <= $now
                    && $promotion->end_at > $now;
            })
            ->sort(function (PromotionItem $left, PromotionItem $right): int {
                $discountComparison = (float) $right->discount_value <=> (float) $left->discount_value;
                if ($discountComparison !== 0) {
                    return $discountComparison;
                }

                $endComparison = $left->promotion->end_at->getTimestamp() <=> $right->promotion->end_at->getTimestamp();

                return $endComparison !== 0
                    ? $endComparison
                    : (int) $left->id <=> (int) $right->id;
            })
            ->first();
    }

    /**
     * @param  array<int, int>  $bookIds
     * @return Collection<int, PromotionItem>
     */
    public function activeItemsForBooks(array $bookIds): Collection
    {
        $bookIds = array_values(array_unique(array_map('intval', $bookIds)));

        if ($bookIds === []) {
            return collect();
        }

        $now = now();

        /** @var Collection<int, PromotionItem> $items */
        $items = PromotionItem::query()
            ->select('promotion_items.*')
            ->with('promotion')
            ->join('promotions', 'promotions.id', '=', 'promotion_items.promotion_id')
            ->whereIn('promotion_items.book_id', $bookIds)
            ->where('promotions.status', PromotionStatus::ACTIVE->value)
            ->where('promotions.start_at', '<=', $now)
            ->where('promotions.end_at', '>', $now)
            ->orderByDesc('promotion_items.discount_value')
            ->orderBy('promotions.end_at')
            ->orderBy('promotion_items.id')
            ->get();

        return $items
            ->unique(fn (PromotionItem $item): int => (int) $item->book_id)
            ->keyBy(fn (PromotionItem $item): int => (int) $item->book_id);
    }
}
