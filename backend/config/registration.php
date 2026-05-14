<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cache store for registration OTP / tokens
    |--------------------------------------------------------------------------
    |
    | Uses Laravel cache (Redis in production per .env). Tests use array driver.
    | Shared with password-reset OTP / reset tokens.
    |
    */

    'cache_store' => env('REGISTRATION_CACHE_STORE', 'redis'),

];
