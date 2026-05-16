<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Guest cart token cookie
    |--------------------------------------------------------------------------
    |
    | Opaque token issued by the API; only a SHA-256 hash is stored in carts.
    | Align path/domain/secure/samesite with session for first-party SPA.
    |
    */

    'guest_token_cookie' => env('GUEST_CART_COOKIE', 'bookify_guest_cart'),

    'guest_token_ttl_days' => (int) env('GUEST_CART_TOKEN_TTL_DAYS', 14),

];
