<?php

namespace App\Services\Cart;

use App\Models\Cart;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class GuestCartTokenService
{
    public static function hash(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    public function getRawTokenFromRequest(): ?string
    {
        $name = (string) config('cart.guest_token_cookie');
        $raw = request()->cookie($name);

        return is_string($raw) && strlen($raw) >= 32 ? $raw : null;
    }

    /**
     * Resolve guest cart by HttpOnly cookie token, or create a new cart and queue a new cookie.
     */
    public function resolveOrCreateGuestCart(): Cart
    {
        try {
            return DB::transaction(function (): Cart {
                $ttlDays = max(1, (int) config('cart.guest_token_ttl_days', 14));
                $expiresAt = now()->addDays($ttlDays);
                $minutes = $ttlDays * 24 * 60;

                $raw = $this->getRawTokenFromRequest();
                if ($raw !== null) {
                    $hash = self::hash($raw);
                    $cart = Cart::query()
                        ->where('guest_token_hash', $hash)
                        ->lockForUpdate()
                        ->first();

                    if ($cart !== null) {
                        if ($cart->guest_token_expires_at !== null && $cart->guest_token_expires_at->isPast()) {
                            $cart->items()->delete();
                            $cart->delete();
                            $this->queueForgetCookie();
                        } else {
                            $cart->guest_token_expires_at = $expiresAt;
                            $cart->save();
                            $this->queueIssueCookie($raw, $minutes);

                            return $cart;
                        }
                    }
                }

                $raw = Str::random(64);
                $hash = self::hash($raw);
                $cart = Cart::query()->create([
                    'account_id' => null,
                    'guest_token_hash' => $hash,
                    'guest_token_expires_at' => $expiresAt,
                ]);
                $this->queueIssueCookie($raw, $minutes);

                return $cart;
            });
        } catch (Throwable $e) {
            Log::error('Resolve guest cart token failed', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function queueForgetCookie(): void
    {
        Cookie::queue(cookie()->forget(
            (string) config('cart.guest_token_cookie'),
            config('session.path'),
            config('session.domain'),
        ));
    }

    private function queueIssueCookie(string $rawToken, int $minutes): void
    {
        Cookie::queue(cookie()->make(
            (string) config('cart.guest_token_cookie'),
            $rawToken,
            $minutes,
            config('session.path'),
            config('session.domain'),
            config('session.secure_cookie'),
            true,
            false,
            config('session.same_site'),
        ));
    }
}
