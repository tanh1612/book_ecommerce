<?php

return [
    'display_limit' => 10,
    'candidate_limit' => 50,
    'popular_ttl_seconds' => 21600,
    'personalized_ttl_seconds' => 3600,
    'user_refresh_debounce_seconds' => 120,
    'user_rebuild_recent_days' => 7,
    'popular_sales_window_days' => 90,
    'popular_recency_window_days' => 60,
    'signal_windows_days' => [
        'view' => 90,
        'cart_add' => 90,
        'purchase' => 365,
    ],
    'minimum_distinct_books' => 5,
    'recent_purchase_exclusion_days' => 90,
    'interaction_retention_days' => 180,
    'content_based' => [
        'category_weight' => 1.5,
        'author_weight' => 2.0,
        'popularity_blend_weight' => 0.1,
    ],
    'weights' => [
        'view' => 1,
        'cart_add' => 3,
        'wishlist' => 4,
        'purchase' => 5,
        'positive_rating' => 5,
        'negative_rating' => -3,
    ],
    'popular_weights' => [
        'sales' => 5,
        'rating' => 3,
        'review_count' => 1,
        'recency' => 1,
    ],
    'view_deduplication_minutes' => 30,
];
