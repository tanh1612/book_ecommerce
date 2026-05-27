<?php

return [

    'base_url' => rtrim((string) env('TINH_THANH_PHO_BASE_URL', 'https://tinhthanhpho.com/api/v1'), '/'),

    'api_key' => env('TINH_THANH_PHO_API_KEY'),

    'timeout' => (int) env('TINH_THANH_PHO_TIMEOUT', 10),

    'cache_ttl_seconds' => (int) env('TINH_THANH_PHO_CACHE_TTL', 60 * 60 * 24 * 7),

    'stale_cache_ttl_seconds' => (int) env('TINH_THANH_PHO_STALE_TTL', 60 * 60 * 24 * 30),

    'cache_key_version' => (string) env('TINH_THANH_PHO_CACHE_VERSION', 'v2025'),

];
