<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Low stock threshold (available quantity)
    |--------------------------------------------------------------------------
    |
    | available_stock = quantity - reserved_quantity
    | Items with available_stock > 0 and <= this value are "low stock".
    |
    */
    'low_stock_threshold' => (int) env('LOW_STOCK_THRESHOLD', 5),

    /*
    |--------------------------------------------------------------------------
    | Low-stock notification preview
    |--------------------------------------------------------------------------
    |
    | Maximum inventory rows shown in the daily admin database notification body.
    |
    */
    'low_stock_notification_preview_limit' => (int) env('LOW_STOCK_NOTIFICATION_PREVIEW_LIMIT', 5),

    /*
    |--------------------------------------------------------------------------
    | Immediate stock alerts (Filament database notifications)
    |--------------------------------------------------------------------------
    |
    | When true, admins are notified as soon as available stock crosses into
    | low or out-of-stock. When false, only the daily summary command applies.
    |
    */
    'low_stock_immediate_notifications' => (bool) env('LOW_STOCK_IMMEDIATE_NOTIFICATIONS', true),

];
