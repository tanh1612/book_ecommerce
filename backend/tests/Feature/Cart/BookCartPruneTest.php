<?php

use App\Models\Account;
use App\Models\Book;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Inventory;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutMiddleware(VerifyCsrfToken::class);
});

function pruneTestBookWithStock(int $available = 10): Book
{
    $book = Book::factory()->create();
    Inventory::factory()->create([
        'book_id' => $book->id,
        'quantity' => $available,
        'reserved_quantity' => 0,
    ]);

    return $book;
}

test('disabling book removes its cart items from all carts', function (): void {
    $bookA = pruneTestBookWithStock();
    $bookB = pruneTestBookWithStock();

    $this->postJson('/api/v1/cart/items', [
        'book_id' => $bookA->id,
        'quantity' => 1,
    ])->assertCreated();

    $this->postJson('/api/v1/cart/items', [
        'book_id' => $bookB->id,
        'quantity' => 2,
    ])->assertCreated();

    expect(CartItem::query()->where('book_id', $bookA->id)->count())->toBe(1)
        ->and(CartItem::query()->where('book_id', $bookB->id)->count())->toBe(1);

    $bookA->update(['is_active' => false]);

    expect(CartItem::query()->where('book_id', $bookA->id)->count())->toBe(0)
        ->and(CartItem::query()->where('book_id', $bookB->id)->count())->toBe(1);
});

test('saving inactive book without is_active change does not re-run prune', function (): void {
    $book = pruneTestBookWithStock();
    $book->update(['is_active' => false]);

    $cart = Cart::query()->create([
        'account_id' => null,
        'guest_token_hash' => hash('sha256', 'orphan-item-test'),
        'guest_token_expires_at' => now()->addDays(30),
    ]);

    CartItem::query()->create([
        'cart_id' => $cart->id,
        'book_id' => $book->id,
        'quantity' => 1,
        'selected' => true,
    ]);

    $book->update(['name' => 'Renamed while inactive']);

    expect(CartItem::query()->where('book_id', $book->id)->count())->toBe(1);
});

test('re-enabling book does not restore cart items', function (): void {
    $book = pruneTestBookWithStock();

    $this->postJson('/api/v1/cart/items', [
        'book_id' => $book->id,
        'quantity' => 1,
    ])->assertCreated();

    $book->update(['is_active' => false]);
    expect(CartItem::query()->where('book_id', $book->id)->count())->toBe(0);

    $book->update(['is_active' => true]);

    expect(CartItem::query()->where('book_id', $book->id)->count())->toBe(0);
});

test('out of stock auto-disable removes book from member cart', function (): void {
    $account = Account::factory()->create();
    $book = pruneTestBookWithStock(2);

    $this->actingAs($account)->postJson('/api/v1/cart/items', [
        'book_id' => $book->id,
        'quantity' => 1,
    ])->assertCreated();

    $inventory = Inventory::query()->where('book_id', $book->id)->firstOrFail();
    $inventory->update([
        'quantity' => 0,
        'reserved_quantity' => 0,
    ]);

    $book->refresh();
    expect($book->is_active)->toBeFalse()
        ->and(CartItem::query()->where('book_id', $book->id)->count())->toBe(0);
});
