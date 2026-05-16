<?php

namespace App\Services\Cart;

use App\Models\Account;
use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class MergeGuestCartService
{
    public function __construct(
        private GuestCartTokenService $guestCartTokenService,
    ) {}

    /**
     * After registration: attach the guest cart identified by cookie token to the new account, or create an empty member cart.
     */
    public function assignGuestCartToNewAccount(?string $rawGuestToken, Account $account): void
    {
        try {
            DB::transaction(function () use ($rawGuestToken, $account): void {
                if ($rawGuestToken !== null) {
                    $hash = GuestCartTokenService::hash($rawGuestToken);
                    $guestCart = Cart::query()
                        ->where('guest_token_hash', $hash)
                        ->lockForUpdate()
                        ->first();

                    if ($guestCart !== null) {
                        $guestCart->forceFill([
                            'account_id' => $account->id,
                            'guest_token_hash' => null,
                            'guest_token_expires_at' => null,
                        ])->save();

                        return;
                    }
                }

                Cart::query()->firstOrCreate(
                    ['account_id' => $account->id],
                    [
                        'guest_token_hash' => null,
                        'guest_token_expires_at' => null,
                    ],
                );
            });

            if ($rawGuestToken !== null) {
                $this->guestCartTokenService->queueForgetCookie();
            }
        } catch (Throwable $e) {
            Log::error('Assign guest cart after register failed', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * After login: merge cart keyed by guest cookie token into the member cart.
     */
    public function mergeGuestCartAfterLogin(?string $rawGuestToken, Account $account): void
    {
        if ($rawGuestToken === null) {
            return;
        }

        try {
            DB::transaction(function () use ($rawGuestToken, $account): void {
                $hash = GuestCartTokenService::hash($rawGuestToken);
                $guestCart = Cart::query()
                    ->where('guest_token_hash', $hash)
                    ->lockForUpdate()
                    ->first();

                if ($guestCart === null) {
                    return;
                }

                $memberCart = Cart::query()
                    ->where('account_id', $account->id)
                    ->lockForUpdate()
                    ->first();

                if ($memberCart === null) {
                    $guestCart->forceFill([
                        'account_id' => $account->id,
                        'guest_token_hash' => null,
                        'guest_token_expires_at' => null,
                    ])->save();

                    return;
                }

                if ($guestCart->id === $memberCart->id) {
                    return;
                }

                foreach ($guestCart->items()->lockForUpdate()->cursor() as $guestItem) {
                    $memberItem = CartItem::query()
                        ->where('cart_id', $memberCart->id)
                        ->where('book_id', $guestItem->book_id)
                        ->lockForUpdate()
                        ->first();

                    if ($memberItem !== null) {
                        $memberItem->update([
                            'quantity' => $memberItem->quantity + $guestItem->quantity,
                            'selected' => $memberItem->selected || $guestItem->selected,
                        ]);
                        $guestItem->delete();
                    } else {
                        $guestItem->update(['cart_id' => $memberCart->id]);
                    }
                }

                $guestCart->delete();
            });

            $this->guestCartTokenService->queueForgetCookie();
        } catch (Throwable $e) {
            Log::error('Merge guest cart after login failed', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
