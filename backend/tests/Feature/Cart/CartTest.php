<?php

use App\Models\Account;
use App\Models\Book;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Inventory;
use App\Models\Warehouse;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutMiddleware(VerifyCsrfToken::class);
});

function createBookWithAvailableStock(int $available = 10): Book
{
    $book = Book::factory()->create();
    Inventory::factory()->create([
        'book_id' => $book->id,
        'warehouse_id' => Warehouse::factory(),
        'quantity' => $available,
        'reserved_quantity' => 0,
    ]);

    return $book;
}

test('guest get cart bootstraps empty cart with guest token cookie', function (): void {
    $response = $this->getJson('/api/v1/cart');

    $response->assertOk()
        ->assertJsonPath('data.items', [])
        ->assertJsonPath('data.total_quantity', 0)
        ->assertCookie(config('cart.guest_token_cookie'));

    $cart = Cart::query()->firstOrFail();
    expect($cart->account_id)->toBeNull()
        ->and($cart->guest_token_hash)->not->toBeNull()
        ->and(strlen((string) $cart->guest_token_hash))->toBe(64);

    $this->getJson('/api/v1/cart')->assertOk()
        ->assertJsonPath('data.id', $cart->id);
});

test('member get cart bootstraps empty cart keyed by account', function (): void {
    $account = Account::factory()->create();

    $this->actingAs($account)->getJson('/api/v1/cart')
        ->assertOk()
        ->assertJsonPath('data.items', []);

    $cart = Cart::query()->where('account_id', $account->id)->firstOrFail();
    expect($cart->guest_token_hash)->toBeNull();
});

test('guest can add merge and update cart items', function (): void {
    $book = createBookWithAvailableStock(5);

    $this->postJson('/api/v1/cart/items', [
        'book_id' => $book->id,
        'quantity' => 2,
    ])->assertCreated()
        ->assertJsonPath('data.total_quantity', 2);

    $this->postJson('/api/v1/cart/items', [
        'book_id' => $book->id,
        'quantity' => 1,
    ])->assertCreated()
        ->assertJsonPath('data.total_quantity', 3);

    $itemId = CartItem::query()->where('book_id', $book->id)->value('id');

    $this->patchJson("/api/v1/cart/items/{$itemId}", [
        'quantity' => 4,
        'selected' => false,
    ])->assertOk()
        ->assertJsonPath('data.total_quantity', 4)
        ->assertJsonPath('data.selected_quantity', 0);

    $this->patchJson("/api/v1/cart/items/{$itemId}", [
        'selected' => true,
    ])->assertOk()
        ->assertJsonPath('data.selected_quantity', 4);
});

test('guest cannot add inactive book to cart', function (): void {
    $book = Book::factory()->inactive()->create();

    $this->postJson('/api/v1/cart/items', [
        'book_id' => $book->id,
        'quantity' => 1,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('book_id');
});

test('guest cannot exceed available stock', function (): void {
    $book = createBookWithAvailableStock(2);

    $this->postJson('/api/v1/cart/items', [
        'book_id' => $book->id,
        'quantity' => 3,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('quantity');
});

test('guest can remove one cart item', function (): void {
    $book = createBookWithAvailableStock(5);
    $this->postJson('/api/v1/cart/items', [
        'book_id' => $book->id,
        'quantity' => 1,
    ])->assertCreated();

    $itemId = CartItem::query()->firstOrFail()->id;

    $this->deleteJson("/api/v1/cart/items/{$itemId}")
        ->assertOk()
        ->assertJsonPath('data.items', []);

    expect(CartItem::query()->count())->toBe(0);
});

test('update cart item requires quantity or selected', function (): void {
    $book = createBookWithAvailableStock(5);
    $this->postJson('/api/v1/cart/items', [
        'book_id' => $book->id,
        'quantity' => 1,
    ])->assertCreated();

    $itemId = CartItem::query()->firstOrFail()->id;

    $this->patchJson("/api/v1/cart/items/{$itemId}", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('quantity');
});

test('cannot update cart item belonging to another guest token', function (): void {
    $book = createBookWithAvailableStock(5);

    $this->postJson('/api/v1/cart/items', [
        'book_id' => $book->id,
        'quantity' => 1,
    ])->assertCreated();

    $itemId = CartItem::query()->firstOrFail()->id;

    $this->resetApplication();

    $this->withoutMiddleware(VerifyCsrfToken::class);

    $this->patchJson("/api/v1/cart/items/{$itemId}", ['quantity' => 2])
        ->assertNotFound();
});

test('guest can select all cart items in one request', function (): void {
    $bookA = createBookWithAvailableStock(5);
    $bookB = createBookWithAvailableStock(5);

    $this->postJson('/api/v1/cart/items', [
        'book_id' => $bookA->id,
        'quantity' => 1,
    ])->assertCreated();

    $this->postJson('/api/v1/cart/items', [
        'book_id' => $bookB->id,
        'quantity' => 2,
    ])->assertCreated();

    $itemIds = CartItem::query()->orderBy('id')->pluck('id');

    foreach ($itemIds as $itemId) {
        $this->patchJson("/api/v1/cart/items/{$itemId}", ['selected' => false])->assertOk();
    }

    $this->patchJson('/api/v1/cart/items/selection', ['selected' => true])
        ->assertOk()
        ->assertJsonPath('data.selected_quantity', 3)
        ->assertJsonPath('data.selected_subtotal', 300_000.0);

    expect(CartItem::query()->where('selected', false)->count())->toBe(0);
});

test('guest can deselect all cart items in one request', function (): void {
    $bookA = createBookWithAvailableStock(5);
    $bookB = createBookWithAvailableStock(5);

    $this->postJson('/api/v1/cart/items', [
        'book_id' => $bookA->id,
        'quantity' => 1,
    ])->assertCreated();

    $this->postJson('/api/v1/cart/items', [
        'book_id' => $bookB->id,
        'quantity' => 1,
    ])->assertCreated();

    $this->patchJson('/api/v1/cart/items/selection', ['selected' => false])
        ->assertOk()
        ->assertJsonPath('data.selected_quantity', 0)
        ->assertJsonPath('data.selected_subtotal', 0.0);

    expect(CartItem::query()->where('selected', true)->count())->toBe(0);
});

test('bulk cart selection requires selected boolean', function (): void {
    $this->getJson('/api/v1/cart')->assertOk();

    $this->patchJson('/api/v1/cart/items/selection', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('selected');

    $this->patchJson('/api/v1/cart/items/selection', ['selected' => 'not-a-boolean'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('selected');
});

test('guest can bulk update selection on empty cart', function (): void {
    $this->getJson('/api/v1/cart')->assertOk();

    $this->patchJson('/api/v1/cart/items/selection', ['selected' => false])
        ->assertOk()
        ->assertJsonPath('data.items', [])
        ->assertJsonPath('data.selected_quantity', 0);
});
