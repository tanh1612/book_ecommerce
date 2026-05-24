<?php

namespace App\Http\Resources\Concerns;

use App\Models\PromotionItem;
use App\Services\Promotion\PromotionPricingService;
use App\Services\Promotion\PromotionResolver;

trait ResolvesBookPromotionPayload
{
    /**
     * @return array{
     *     effective_price: float,
     *     discount_amount: float,
     *     promotion: array<string, mixed>|null
     * }
     */
    private function promotionPayload(): array
    {
        $unitPrice = (string) $this->selling_price;
        $promotionItem = app(PromotionResolver::class)
            ->activeItemsForBooks([(int) $this->id])
            ->get((int) $this->id);

        if (! $promotionItem instanceof PromotionItem) {
            return [
                'effective_price' => (float) $unitPrice,
                'discount_amount' => 0.0,
                'promotion' => null,
            ];
        }

        $pricing = app(PromotionPricingService::class)->calculateLine(
            $unitPrice,
            1,
            (string) $promotionItem->discount_value,
        );

        return [
            'effective_price' => (float) $pricing['line_total'],
            'discount_amount' => (float) $pricing['discount_amount'],
            'promotion' => [
                'id' => $promotionItem->promotion_id,
                'item_id' => $promotionItem->id,
                'type' => $promotionItem->promotion?->type?->value,
                'name' => $promotionItem->promotion?->name,
                'discount_percent' => (float) $promotionItem->discount_value,
                'start_at' => $promotionItem->promotion?->start_at?->toIso8601String(),
                'end_at' => $promotionItem->promotion?->end_at?->toIso8601String(),
                'stock_limit' => $promotionItem->stock_limit,
                'remaining_stock' => $promotionItem->stock_limit === null
                    ? null
                    : max(0, (int) $promotionItem->stock_limit - (int) $promotionItem->sold_quantity),
                'max_quantity_per_user' => $promotionItem->max_quantity_per_user,
            ],
        ];
    }
}
