<?php

return [

    'support_hotline' => env('REFUND_SUPPORT_HOTLINE', ''),

    'manual_refund_deadline_days' => (int) env('REFUND_MANUAL_DEADLINE_DAYS', 15),

    'bank_catalog' => [
        'cache_ttl_seconds' => (int) env('REFUND_BANKS_CACHE_TTL_SECONDS', 86400),
        'vietqr' => [
            'banks_url' => env('VIETQR_BANKS_URL', 'https://api.vietqr.io/v2/banks'),
        ],
    ],

    /**
     * VietQR short code => AppotaPay firm-banking bankCode.
     */
    'appotapay_bank_code_map' => [
        'VTB' => 'VIETINBANK',
        'ICB' => 'VIETINBANK',
        'TCB' => 'TECHCOMBANK',
        'TPB' => 'TPBANK',
        'STB' => 'SACOMBANK',
        'AGR' => 'AGRIBANK',
        'VPB' => 'VPBANK',
        'EIB' => 'EXIMBANK',
        'MSB' => 'MARITIMEBANK',
        'HDB' => 'HDBANK',
        'VBA' => 'VIETABANK',
        'NAB' => 'NAMABANK',
        'SEA' => 'SEABANK',
        'ABB' => 'ABBANK',
        'PVB' => 'PVBANK',
        'BVB' => 'BVBANK',
        'KLB' => 'KIENLONGBANK',
        'PGB' => 'PGBANK',
        'GPB' => 'GPBANK',
        'SGICB' => 'SAIGONBANK',
        'DAB' => 'DAB',
        'BAB' => 'BACA',
        'VCCB' => 'VIETCAPITALBANK',
        'CAKE' => 'CAKE',
        'TIMO' => 'TIMO',
        'UBANK' => 'UBANK',
        'LIOBANK' => 'LIOBANK',
        'CBB' => 'CBBANK',
        'WVN' => 'WOORIBANK',
        'HLB' => 'HONGLEONG',
        'SHBVN' => 'SHINHAN',
        'IBKHN' => 'IBK',
        'IBKHCM' => 'IBK',
        'KBHN' => 'KBHN',
        'KBHCM' => 'KBHCM',
        'KEBHANAHN' => 'KEBHN',
        'KEBHANAHCM' => 'KEBHCM',
        'CITIBANK' => 'CITIBANK',
        'SCVN' => 'SCVN',
        'NHB' => 'NHB',
        'KBANK' => 'KBANK',
        'UMEE' => 'UMEE',
        'COOPBANK' => 'COOPBANK',
        'VIETBANK' => 'VIETBANK',
        'OCEANBANK' => 'OCEANBANK',
    ],

    'verification' => [
        'driver' => env('REFUND_BANK_VERIFICATION_DRIVER', 'log'),
        'appotapay' => [
            'base_url' => env('APPOTAPAY_BASE_URL', 'https://gateway.dev.appotapay.com'),
            'partner_code' => env('APPOTAPAY_PARTNER_CODE'),
            'api_key' => env('APPOTAPAY_API_KEY'),
            'secret_key' => env('APPOTAPAY_SECRET_KEY'),
            'jwt_ttl_seconds' => (int) env('APPOTAPAY_JWT_TTL_SECONDS', 300),
        ],
    ],
];
