<?php

namespace App\Services\Promotion;

use App\Models\Book;
use App\Models\PromotionItem;
use Illuminate\Support\Collection;

class PromotionQuoteService
{
    public function __construct(
        private FlashSaleResolver $flashSaleResolver,
        private PromotionPricingService $promotionPricing,
    ) {}

    /**
     * @return array{
     *     base_unit_price: float,
     *     effective_unit_price: float,
     *     discount_amount: float,
     *     line_subtotal_before_discount: float,
     *     line_total: float,
     *     promotion: array<string, mixed>|null
     * }
     */
    public function quoteLine(Book $book, int $quantity, ?PromotionItem $promotionItem = null): array
    {
        $quantity = max(1, $quantity);
        $baseUnitPrice = (string) $book->selling_price;

        $pricing = $this->promotionPricing->calculateLine(
            $baseUnitPrice,
            $quantity,
            $promotionItem !== null ? (string) $promotionItem->discount_value : null,
        );

        $effectiveUnitPrice = bcdiv($pricing['line_total'], (string) $quantity, 2);

        return [
            'base_unit_price' => (float) $baseUnitPrice,
            'effective_unit_price' => (float) $effectiveUnitPrice,
            'discount_amount' => (float) $pricing['discount_amount'],
            'line_subtotal_before_discount' => (float) $pricing['line_subtotal'],
            'line_total' => (float) $pricing['line_total'],
            'promotion' => $promotionItem !== null
                ? $this->formatPromotionMeta($promotionItem)
                : null,
        ];
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
    ): Collection {
        return $this->flashSaleResolver->activeItemsForBooks($bookIds, $accountId, $quantityByBookId);
    }

    /**
     * @return array<string, mixed>
     */
    public function formatPromotionMeta(PromotionItem $promotionItem): array
    {
        $promotion = $promotionItem->promotion;

        return [
            'id' => $promotionItem->promotion_id,
            'item_id' => $promotionItem->id,
            'name' => $promotion?->name,
            'type' => $promotion?->type?->value,
            'discount_percent' => (float) $promotionItem->discount_value,
            'start_at' => $promotion?->start_at?->toIso8601String(),
            'end_at' => $promotion?->end_at?->toIso8601String(),
            'stock_limit' => $promotionItem->stock_limit,
            'remaining_stock' => $promotionItem->stock_limit === null
                ? null
                : max(0, (int) $promotionItem->stock_limit - (int) $promotionItem->sold_quantity),
        ];
    }
}
