<?php

return [

    'manual_refund_deadline_days' => (int) env('REFUND_MANUAL_DEADLINE_DAYS', 15),

    'bank_catalog' => [
        'cache_ttl_seconds' => (int) env('REFUND_BANKS_CACHE_TTL_SECONDS', 86400),
        'vietqr' => [
            'banks_url' => env('VIETQR_BANKS_URL', 'https://api.vietqr.io/v2/banks'),
        ],
    ],
];
