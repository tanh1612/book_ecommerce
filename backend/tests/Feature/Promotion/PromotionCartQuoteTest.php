<?php

use App\Models\Account;
use App\Models\Book;
use App\Models\Inventory;
use App\Models\Promotion;
use App\Models\PromotionItem;
use App\Models\Warehouse;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    test()->withoutMiddleware(VerifyCsrfToken::class);
    test()->disableCookieEncryption();
    test()->withCredentials();
});

function cartQuoteGuestCookieFrom(\Illuminate\Testing\TestResponse $response): void
{
    $cookieName = (string) config('cart.guest_token_cookie');

    foreach ($response->baseResponse->headers->getCookies() as $cookie) {
        if ($cookie->getName() !== $cookieName) {
            continue;
        }

        test()->withCookie(
            $cookie->getName(),
            $cookie->getValue(),
            $cookie->getExpiresTime(),
            $cookie->getPath(),
            $cookie->getDomain(),
            $cookie->isSecure(),
            $cookie->isHttpOnly(),
            false,
            $cookie->getSameSite(),
        );

        return;
    }

    test()->fail('Guest cart cookie missing from response');
}

function cartQuoteBookWithStock(): Book
{
    $book = Book::factory()->create(['selling_price' => 100000]);
    Inventory::factory()->create([
        'book_id' => $book->id,
        'warehouse_id' => Warehouse::factory(),
        'quantity' => 10,
        'reserved_quantity' => 0,
    ]);

    return $book;
}

test('cart without promotion returns regular pricing fields', function (): void {
    $account = Account::factory()->create();
    $book = cartQuoteBookWithStock();

    $this->actingAs($account)->postJson('/api/v1/cart/items', [
        'book_id' => $book->id,
        'quantity' => 2,
    ])->assertCreated();

    $this->actingAs($account)->getJson('/api/v1/cart')
        ->assertOk()
        ->assertJsonPath('data.items.0.base_unit_price', 100000)
        ->assertJsonPath('data.items.0.effective_unit_price', 100000)
        ->assertJsonPath('data.items.0.promotion', null)
        ->assertJsonPath('data.selected_discount_total', 0)
        ->assertJsonPath('data.selected_subtotal_after_discount', 200000);
});

test('cart with active promotion returns discounted quote', function (): void {
    $account = Account::factory()->create();
    $book = cartQuoteBookWithStock();

    $promotion = Promotion::query()->create([
        'name' => 'Cart quote promo',
        'type' => 'flash_sale',
        'start_at' => now()->subMinute(),
        'end_at' => now()->addHour(),
        'status' => 'active',
    ]);

    $item = PromotionItem::query()->create([
        'promotion_id' => $promotion->id,
        'book_id' => $book->id,
        'discount_value' => 10,
    ]);

    $this->actingAs($account)->postJson('/api/v1/cart/items', [
        'book_id' => $book->id,
        'quantity' => 2,
    ])->assertCreated();

    $this->actingAs($account)->getJson('/api/v1/cart')
        ->assertOk()
        ->assertJsonPath('data.items.0.promotion.item_id', $item->id)
        ->assertJsonPath('data.items.0.effective_unit_price', 90000)
        ->assertJsonMissingPath('data.items.0.promotion.max_quantity_per_user')
        ->assertJsonPath('data.selected_discount_total', 20000)
        ->assertJsonPath('data.selected_subtotal_after_discount', 180000);
});

test('cart returns regular price when promotion stock is exhausted', function (): void {
    $account = Account::factory()->create();
    $book = cartQuoteBookWithStock();

    $promotion = Promotion::query()->create([
        'name' => 'Sold out promo',
        'type' => 'flash_sale',
        'start_at' => now()->subMinute(),
        'end_at' => now()->addHour(),
        'status' => 'active',
    ]);

    PromotionItem::query()->create([
        'promotion_id' => $promotion->id,
        'book_id' => $book->id,
        'discount_value' => 20,
        'stock_limit' => 2,
        'sold_quantity' => 2,
    ]);

    $this->actingAs($account)->postJson('/api/v1/cart/items', [
        'book_id' => $book->id,
        'quantity' => 1,
    ])->assertCreated();

    $this->actingAs($account)->getJson('/api/v1/cart')
        ->assertOk()
        ->assertJsonPath('data.items.0.promotion', null)
        ->assertJsonPath('data.items.0.effective_unit_price', 100000)
        ->assertJsonPath('data.items.0.line_subtotal_before_discount', 100000)
        ->assertJsonPath('data.pricing_expectations', []);
});

test('cart returns pricing expectations for selected flash sale lines', function (): void {
    $account = Account::factory()->create();
    $book = cartQuoteBookWithStock();

    $promotion = Promotion::query()->create([
        'name' => 'Checkout quote promo',
        'type' => 'flash_sale',
        'start_at' => now()->subMinute(),
        'end_at' => now()->addHour(),
        'status' => 'active',
    ]);

    $item = PromotionItem::query()->create([
        'promotion_id' => $promotion->id,
        'book_id' => $book->id,
        'discount_value' => 26,
        'stock_limit' => 1,
    ]);

    $this->actingAs($account)->postJson('/api/v1/cart/items', [
        'book_id' => $book->id,
        'quantity' => 1,
    ])->assertCreated();

    $this->actingAs($account)->getJson('/api/v1/cart')
        ->assertOk()
        ->assertJsonPath('data.pricing_expectations.0.book_id', $book->id)
        ->assertJsonPath('data.pricing_expectations.0.promotion_item_id', $item->id)
        ->assertJsonPath('data.pricing_expectations.0.effective_unit_price', 74000)
        ->assertJsonPath('data.pricing_expectations.0.line_total', 74000);
});

test('cart omits pricing expectations for deselected flash sale lines', function (): void {
    $account = Account::factory()->create();
    $book = cartQuoteBookWithStock();

    Promotion::query()->create([
        'name' => 'Deselected flash',
        'type' => 'flash_sale',
        'start_at' => now()->subMinute(),
        'end_at' => now()->addHour(),
        'status' => 'active',
    ])->items()->create([
        'book_id' => $book->id,
        'discount_value' => 10,
    ]);

    $add = $this->actingAs($account)->postJson('/api/v1/cart/items', [
        'book_id' => $book->id,
        'quantity' => 1,
    ])->assertCreated();

    $cartItemId = $add->json('data.cart_item_id');

    $this->actingAs($account)->patchJson("/api/v1/cart/items/{$cartItemId}", [
        'selected' => false,
    ])->assertOk()
        ->assertJsonPath('data.selected', false)
        ->assertJsonPath('data.selected_quantity', 0);

    $this->actingAs($account)->getJson('/api/v1/cart')
        ->assertOk()
        ->assertJsonPath('data.pricing_expectations', []);
});

test('guest cart quote does not enforce max quantity per user', function (): void {
    $book = cartQuoteBookWithStock();

    $promotion = Promotion::query()->create([
        'name' => 'Guest per-user promo',
        'type' => 'flash_sale',
        'start_at' => now()->subMinute(),
        'end_at' => now()->addHour(),
        'status' => 'active',
    ]);

    PromotionItem::query()->create([
        'promotion_id' => $promotion->id,
        'book_id' => $book->id,
        'discount_value' => 20,
        'max_quantity_per_user' => 1,
    ]);

    $add = $this->postJson('/api/v1/cart/items', [
        'book_id' => $book->id,
        'quantity' => 2,
    ])->assertCreated();

    cartQuoteGuestCookieFrom($add);

    $this->getJson('/api/v1/cart')
        ->assertOk()
        ->assertJsonPath('data.items.0.promotion.discount_percent', 20)
        ->assertJsonPath('data.items.0.effective_unit_price', 80000);
});

test('member cart quote enforces max quantity per user from prior allocations', function (): void {
    $account = Account::factory()->create();
    $book = cartQuoteBookWithStock();

    $promotion = Promotion::query()->create([
        'name' => 'Member per-user promo',
        'type' => 'flash_sale',
        'start_at' => now()->subMinute(),
        'end_at' => now()->addHour(),
        'status' => 'active',
    ]);

    $item = PromotionItem::query()->create([
        'promotion_id' => $promotion->id,
        'book_id' => $book->id,
        'discount_value' => 15,
        'max_quantity_per_user' => 2,
    ]);

    \App\Models\PromotionAllocation::query()->create([
        'promotion_item_id' => $item->id,
        'account_id' => $account->id,
        'order_id' => null,
        'order_item_id' => null,
        'quantity' => 1,
        'status' => \App\Enums\Promotion\PromotionAllocationStatus::RESERVED,
    ]);

    $this->actingAs($account)->postJson('/api/v1/cart/items', [
        'book_id' => $book->id,
        'quantity' => 2,
    ])->assertCreated();

    $this->actingAs($account)->getJson('/api/v1/cart')
        ->assertOk()
        ->assertJsonPath('data.items.0.promotion', null)
        ->assertJsonPath('data.items.0.effective_unit_price', 100000);
});

test('cart does not apply cancelled promotion', function (): void {
    $account = Account::factory()->create();
    $book = cartQuoteBookWithStock();

    $promotion = Promotion::query()->create([
        'name' => 'Cancelled promo',
        'type' => 'flash_sale',
        'start_at' => now()->subHour(),
        'end_at' => now()->addHour(),
        'status' => 'cancelled',
    ]);

    PromotionItem::query()->create([
        'promotion_id' => $promotion->id,
        'book_id' => $book->id,
        'discount_value' => 50,
    ]);

    $this->actingAs($account)->postJson('/api/v1/cart/items', [
        'book_id' => $book->id,
        'quantity' => 1,
    ])->assertCreated();

    $this->actingAs($account)->getJson('/api/v1/cart')
        ->assertOk()
        ->assertJsonPath('data.items.0.promotion', null)
        ->assertJsonPath('data.items.0.effective_unit_price', 100000);
});
