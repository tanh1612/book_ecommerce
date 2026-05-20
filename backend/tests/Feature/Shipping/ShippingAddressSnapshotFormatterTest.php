<?php

use App\Services\Shipping\ShippingAddressSnapshotFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('formats shipping address snapshot with administrative names', function (): void {
    Http::fake([
        '*new-full-address*' => Http::response([
            'success' => true,
            'data' => [
                'province' => [
                    'code' => '01',
                    'name' => 'Hà Nội',
                    'type' => 'Thành phố',
                ],
                'ward' => [
                    'code' => '00001',
                    'name' => 'Phúc Xá',
                    'type' => 'Phường',
                    'province_code' => '01',
                ],
            ],
        ], 200),
    ]);

    $formatted = app(ShippingAddressSnapshotFormatter::class)->format(
        'Số 123 Đường ABC',
        '00001',
        '01',
    );

    expect($formatted)->toBe('Số 123 Đường ABC, phường Phúc Xá, Thành phố Hà Nội');
});
