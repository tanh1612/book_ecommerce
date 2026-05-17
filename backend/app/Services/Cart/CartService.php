<?php

namespace App\Services\Cart;

use App\Models\Account;
use App\Models\Book;
use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class CartService
{
    public function __construct(
        private GuestCartTokenService $guestCartTokenService,
    ) {}

    /**
     * Resolve or create the single active cart for the current request identity.
     */
    public function getCurrentCart(): Cart
    {
        try {
            /** @var Account|null $account */
            $account = Auth::guard('web')->user();

            if ($account !== null) {
                return Cart::query()->firstOrCreate(
                    ['account_id' => $account->id],
                    [
                        'guest_token_hash' => null,
                        'guest_token_expires_at' => null,
                    ],
                );
            }

            return $this->guestCartTokenService->resolveOrCreateGuestCart();
        } catch (Throwable $e) {
            Log::error('Resolve current cart failed', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Cart with items and book relations for API responses (includes inactive books already in cart).
     */
    public function getCurrentCartForApi(): Cart
    {
        $cart = $this->getCurrentCart();

        return $cart->load([
            'items' => fn ($q) => $q->orderBy('id'),
            'items.book' => fn ($q) => $q->with([
                'authors',
                'categories',
                'publisher',
                'images',
                'inventories',
            ]),
        ]);
    }

    public function addItem(int $bookId, int $quantity): Cart
    {
        try {
            return DB::transaction(function () use ($bookId, $quantity): Cart {
                $cart = Cart::query()->whereKey($this->getCurrentCart()->id)->lockForUpdate()->firstOrFail();

                $book = Book::query()
                    ->active()
                    ->whereKey($bookId)
                    ->lockForUpdate()
                    ->first();

                if ($book === null) {
                    throw ValidationException::withMessages([
                        'book_id' => ['Sách không tồn tại hoặc đã ngừng kinh doanh.'],
                    ]);
                }

                $available = $this->availableStockForBook($book->id);

                $existing = CartItem::query()
                    ->where('cart_id', $cart->id)
                    ->where('book_id', $bookId)
                    ->lockForUpdate()
                    ->first();

                $currentQty = $existing?->quantity ?? 0;
                $newTotal = $currentQty + $quantity;

                if ($newTotal > $available) {
                    throw ValidationException::withMessages([
                        'quantity' => ['Số lượng vượt quá tồn kho khả dụng.'],
                    ]);
                }

                if ($existing !== null) {
                    $existing->update(['quantity' => $newTotal]);
                } else {
                    CartItem::query()->create([
                        'cart_id' => $cart->id,
                        'book_id' => $bookId,
                        'quantity' => $quantity,
                        'selected' => true,
                    ]);
                }

                return $cart;
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Add cart item failed', [
                'book_id' => $bookId,
                'quantity' => $quantity,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @param  array{quantity?: int, selected?: bool}  $data
     */
    public function updateItem(CartItem $cartItem, array $data): Cart
    {
        try {
            return DB::transaction(function () use ($cartItem, $data): Cart {
                $cart = Cart::query()->whereKey($this->getCurrentCart()->id)->lockForUpdate()->firstOrFail();

                $lockedItem = CartItem::query()
                    ->whereKey($cartItem->id)
                    ->where('cart_id', $cart->id)
                    ->lockForUpdate()
                    ->first();

                if ($lockedItem === null) {
                    abort(404);
                }

                if (array_key_exists('quantity', $data)) {
                    $bookId = (int) $lockedItem->book_id;
                    $available = $this->availableStockForBook($bookId);
                    $qty = (int) $data['quantity'];

                    if ($qty > $available) {
                        throw ValidationException::withMessages([
                            'quantity' => ['Số lượng vượt quá tồn kho khả dụng.'],
                        ]);
                    }

                    $lockedItem->update(['quantity' => $qty]);
                }

                if (array_key_exists('selected', $data)) {
                    $lockedItem->update(['selected' => (bool) $data['selected']]);
                }

                return $cart;
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Update cart item failed', [
                'cart_item_id' => $cartItem->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function updateItemsSelection(bool $selected): Cart
    {
        try {
            return DB::transaction(function () use ($selected): Cart {
                $cart = Cart::query()->whereKey($this->getCurrentCart()->id)->lockForUpdate()->firstOrFail();

                CartItem::query()
                    ->where('cart_id', $cart->id)
                    ->update(['selected' => $selected]);

                return $cart;
            });
        } catch (Throwable $e) {
            Log::error('Update cart items selection failed', [
                'selected' => $selected,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function removeItem(CartItem $cartItem): Cart
    {
        try {
            return DB::transaction(function () use ($cartItem): Cart {
                $cart = Cart::query()->whereKey($this->getCurrentCart()->id)->lockForUpdate()->firstOrFail();

                $lockedItem = CartItem::query()
                    ->whereKey($cartItem->id)
                    ->where('cart_id', $cart->id)
                    ->lockForUpdate()
                    ->first();

                if ($lockedItem === null) {
                    abort(404);
                }

                $lockedItem->delete();

                return $cart;
            });
        } catch (Throwable $e) {
            Log::error('Remove cart item failed', [
                'cart_item_id' => $cartItem->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function availableStockForBook(int $bookId): int
    {
        return (int) DB::table('inventories')
            ->where('book_id', $bookId)
            ->selectRaw('COALESCE(SUM(GREATEST(quantity - reserved_quantity, 0)), 0) as available')
            ->value('available');
    }
}
