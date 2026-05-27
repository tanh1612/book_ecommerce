<?php

namespace App\Http\Resources;

use App\Models\Book;
use App\Models\Cart;
use App\Models\CartItem;
use App\Services\Promotion\PromotionQuoteService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Cart
 */
class CartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Cart $cart */
        $cart = $this->resource;
        $items = $cart->relationLoaded('items') ? $cart->items : collect();

        $bookIds = $items
            ->pluck('book_id')
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $quantityByBookId = $items
            ->mapWithKeys(static fn (CartItem $item): array => [(int) $item->book_id => (int) $item->quantity])
            ->all();

        $accountId = $request->user()?->id !== null ? (int) $request->user()->id : null;

        $promotionItems = app(PromotionQuoteService::class)->activeItemsForBooks(
            $bookIds,
            $accountId,
            $quantityByBookId,
        );

        $subtotalBeforeDiscount = 0.0;
        $discountTotal = 0.0;
        $subtotalAfterDiscount = 0.0;
        $selectedSubtotalBeforeDiscount = 0.0;
        $selectedDiscountTotal = 0.0;
        $selectedSubtotalAfterDiscount = 0.0;
        $totalQuantity = 0;
        $selectedQuantity = 0;

        /** @var list<array{book_id: int, promotion_item_id: int, effective_unit_price: float, line_total: float}> $pricingExpectations */
        $pricingExpectations = [];

        $itemResources = $items->map(function (CartItem $item) use (
            $promotionItems,
            &$subtotalBeforeDiscount,
            &$discountTotal,
            &$subtotalAfterDiscount,
            &$selectedSubtotalBeforeDiscount,
            &$selectedDiscountTotal,
            &$selectedSubtotalAfterDiscount,
            &$totalQuantity,
            &$selectedQuantity,
            &$pricingExpectations,
        ): CartItemResource {
            $book = $item->book;
            $promotionItem = $book instanceof Book
                ? $promotionItems->get((int) $book->id)
                : null;

            $quote = $book instanceof Book
                ? app(PromotionQuoteService::class)->quoteLine($book, (int) $item->quantity, $promotionItem)
                : [
                    'line_subtotal_before_discount' => 0.0,
                    'discount_amount' => 0.0,
                    'line_total' => 0.0,
                ];

            $subtotalBeforeDiscount += $quote['line_subtotal_before_discount'];
            $discountTotal += $quote['discount_amount'];
            $subtotalAfterDiscount += $quote['line_total'];
            $totalQuantity += (int) $item->quantity;

            if ($item->selected) {
                $selectedSubtotalBeforeDiscount += $quote['line_subtotal_before_discount'];
                $selectedDiscountTotal += $quote['discount_amount'];
                $selectedSubtotalAfterDiscount += $quote['line_total'];
                $selectedQuantity += (int) $item->quantity;

                $promotion = $quote['promotion'] ?? null;

                if (is_array($promotion) && isset($promotion['item_id'])) {
                    $pricingExpectations[] = [
                        'book_id' => (int) $item->book_id,
                        'promotion_item_id' => (int) $promotion['item_id'],
                        'effective_unit_price' => round((float) $quote['effective_unit_price'], 2),
                        'line_total' => round((float) $quote['line_total'], 2),
                    ];
                }
            }

            return new CartItemResource($item, $quote);
        });

        return [
            'id' => $cart->id,
            'items' => $itemResources->values(),
            'subtotal_before_discount' => round($subtotalBeforeDiscount, 2),
            'discount_total' => round($discountTotal, 2),
            'subtotal_after_discount' => round($subtotalAfterDiscount, 2),
            'total_quantity' => $totalQuantity,
            'selected_subtotal_before_discount' => round($selectedSubtotalBeforeDiscount, 2),
            'selected_discount_total' => round($selectedDiscountTotal, 2),
            'selected_subtotal_after_discount' => round($selectedSubtotalAfterDiscount, 2),
            'selected_quantity' => $selectedQuantity,
            'pricing_expectations' => $pricingExpectations,
        ];
    }
}
