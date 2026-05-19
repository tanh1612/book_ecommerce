<?php

return [

    'tmn_code' => env('VNP_TMN_CODE'),

    'hash_secret' => env('VNP_HASH_SECRET'),

    'payment_url' => env('VNP_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'),

    'return_url' => env('VNP_RETURN_URL'),

    'payment_ttl_hours' => (int) env('VNP_PAYMENT_TTL_HOURS', 12),

    'version' => '2.1.0',

    'command' => 'pay',

    'curr_code' => 'VND',

    'locale' => 'vn',

];
