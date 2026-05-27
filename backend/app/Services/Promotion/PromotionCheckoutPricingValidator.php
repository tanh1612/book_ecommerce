<?php

namespace App\Services\Promotion;

use App\Exceptions\Promotion\PromotionUnavailableException;
use App\Models\Account;
use App\Models\Book;
use App\Models\CartItem;
use App\Models\PromotionItem;
use Illuminate\Support\Collection;

class PromotionCheckoutPricingValidator
{
    public function __construct(
        private PromotionQuoteService $promotionQuote,
        private FlashSaleResolver $flashSaleResolver,
    ) {}

    /**
     * @param  Collection<int, CartItem>  $cartItems
     * @param  Collection<int, Book>  $books
     * @param  Collection<int, PromotionItem>  $promotionItems
     * @param  array<int, array{book_id: int, promotion_item_id?: int|null, effective_unit_price: float|string, line_total: float|string}>  $expectations
     */
    public function validate(
        Account $account,
        Collection $cartItems,
        Collection $books,
        Collection $promotionItems,
        array $expectations,
    ): void {
        $expectationsByBook = collect($expectations)->keyBy(
            fn (array $row): int => (int) $row['book_id'],
        );

        foreach ($cartItems as $line) {
            $bookId = (int) $line->book_id;
            $book = $books->get($bookId);

            if (! $book instanceof Book) {
                throw new PromotionUnavailableException;
            }

            $qty = (int) $line->quantity;
            $resolvedItem = $promotionItems->get($bookId);
            $quote = $this->promotionQuote->quoteLine($book, $qty, $resolvedItem);
            $expectation = $expectationsByBook->get($bookId);

            if ($resolvedItem === null) {
                if ($expectation !== null && ($expectation['promotion_item_id'] ?? null) !== null) {
                    throw new PromotionUnavailableException;
                }

                continue;
            }

            if (! $this->flashSaleResolver->isItemApplicableForQuantity($resolvedItem, $qty, (int) $account->id)
                || $expectation === null) {
                throw new PromotionUnavailableException;
            }

            $expectedItemId = isset($expectation['promotion_item_id'])
                ? ($expectation['promotion_item_id'] !== null ? (int) $expectation['promotion_item_id'] : null)
                : null;
            $resolvedItemId = $resolvedItem?->id;

            if ($expectedItemId !== $resolvedItemId) {
                throw new PromotionUnavailableException;
            }

            $expectedUnitPrice = number_format((float) $expectation['effective_unit_price'], 2, '.', '');
            $actualUnitPrice = number_format($quote['effective_unit_price'], 2, '.', '');

            if ($expectedUnitPrice !== $actualUnitPrice) {
                throw new PromotionUnavailableException;
            }

            $expectedLineTotal = number_format((float) $expectation['line_total'], 2, '.', '');
            $actualLineTotal = number_format($quote['line_total'], 2, '.', '');

            if ($expectedLineTotal !== $actualLineTotal) {
                throw new PromotionUnavailableException;
            }
        }
    }

}
