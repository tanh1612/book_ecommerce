<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

require_once __DIR__.'/Feature/Ai/Support/AiChatTestHelpers.php';

function checkoutBookWithStock(int $available = 10): \App\Models\Book
{
    $book = \App\Models\Book::factory()->create();
    \App\Models\Inventory::factory()->create([
        'book_id' => $book->id,
        'warehouse_id' => \App\Models\Warehouse::factory(),
        'quantity' => $available,
        'reserved_quantity' => 0,
    ]);

    return $book;
}

function checkoutShippingMethodForProvince(string $provinceCode = '01'): \App\Models\ShippingMethod
{
    $method = \App\Models\ShippingMethod::query()->create([
        'name' => 'Standard',
        'description' => null,
        'is_active' => true,
    ]);

    \App\Models\ShippingRate::query()->create([
        'shipping_method_id' => $method->id,
        'province_code' => $provinceCode,
        'base_fee' => 30000,
    ]);

    return $method;
}

/**
 * @return array<int, array{book_id: int, promotion_item_id: int|null, effective_unit_price: float, line_total: float}>
 */
function checkoutPricingExpectationsForBook(
    \App\Models\Book $book,
    int $quantity = 1,
    ?\App\Models\PromotionItem $promotionItem = null,
): array {
    $resolvedItem = $promotionItem;

    if ($resolvedItem === null) {
        $resolvedItem = app(\App\Services\Promotion\FlashSaleResolver::class)
            ->activeItemsForBooks(
                [(int) $book->id],
                null,
                [(int) $book->id => $quantity],
            )
            ->get((int) $book->id);
    }

    $quote = app(\App\Services\Promotion\PromotionQuoteService::class)->quoteLine(
        $book,
        $quantity,
        $resolvedItem,
    );

    return [[
        'book_id' => (int) $book->id,
        'promotion_item_id' => $resolvedItem?->id,
        'effective_unit_price' => $quote['effective_unit_price'],
        'line_total' => $quote['line_total'],
    ]];
}

function reserveTestOrderItem(
    \App\Models\Account $account,
    \App\Models\Book $book,
    \App\Models\Promotion $promotion,
    \App\Models\PromotionItem $item,
): \App\Models\OrderItem {
    $shipping = \App\Models\ShippingMethod::query()->create([
        'name' => 'Std',
        'description' => null,
        'is_active' => true,
    ]);

    $order = \App\Models\Order::query()->create([
        'account_id' => $account->id,
        'shipping_method_id' => $shipping->id,
        'total_amount' => 100000,
        'shipping_fee' => 0,
        'final_amount' => 100000,
        'shipping_name' => 'Buyer',
        'shipping_phone' => '0900000000',
        'shipping_address' => 'Addr',
        'payment_method' => 'cod',
        'payment_status' => 'pending',
        'current_status' => 'confirmed',
    ]);

    return \App\Models\OrderItem::query()->create([
        'order_id' => $order->id,
        'book_id' => $book->id,
        'promotion_id' => $promotion->id,
        'promotion_item_id' => $item->id,
        'price' => 90000,
        'quantity' => 1,
        'total_price' => 90000,
        'discount_amount' => 10000,
        'is_reviewed' => false,
    ]);
}
