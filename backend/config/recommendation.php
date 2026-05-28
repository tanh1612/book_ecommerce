<?php

return [
    'display_limit' => 10,
    'candidate_limit' => 50,
    'popular_ttl_seconds' => 21600,
    'popular_sales_window_days' => 90,
    'popular_recency_window_days' => 60,
    'popular_weights' => [
        'sales' => 5,
        'rating' => 3,
        'review_count' => 1,
        'recency' => 1,
    ],
    'view_deduplication_minutes' => 30,
];
