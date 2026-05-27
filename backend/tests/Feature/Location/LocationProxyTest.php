<?php

use App\Services\Location\LocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    Http::preventStrayRequests();
});

function locationProvincesCacheKey(): string
{
    $service = app(LocationService::class);
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('cacheKey');
    $method->setAccessible(true);

    return $method->invoke($service, 'new_provinces', []);
}

test('provinces proxy returns upstream shaped payload', function (): void {
    Http::fake([
        '*new-provinces*' => Http::response([
            'success' => true,
            'data' => [
                ['code' => '01', 'name' => 'Hà Nội', 'type' => 'Thành phố'],
            ],
            'metadata' => ['total' => 1, 'page' => 1, 'limit' => 20],
        ], 200),
    ]);

    $this->getJson('/api/v1/locations/provinces')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.code', '01');

    Http::assertSentCount(1);
});

test('wards proxy returns data for province', function (): void {
    Http::fake([
        '*new-provinces/01/wards*' => Http::response([
            'success' => true,
            'data' => [
                ['code' => '00070', 'name' => 'Hoàn Kiếm', 'type' => 'Phường', 'province_code' => '01'],
            ],
            'metadata' => ['total' => 1, 'page' => 1, 'limit' => 20],
        ], 200),
    ]);

    $this->getJson('/api/v1/locations/provinces/01/wards')
        ->assertOk()
        ->assertJsonPath('data.0.code', '00070');
});

test('provinces proxy returns 503 when upstream fails', function (): void {
    Http::fake([
        '*new-provinces*' => Http::response('', 500),
    ]);

    $this->getJson('/api/v1/locations/provinces')->assertStatus(503);

    Http::assertSentCount(1);
});

test('location service serves stale backup when primary missing and upstream fails', function (): void {
    $payload = [
        'success' => true,
        'data' => [
            ['code' => '01', 'name' => 'Hà Nội', 'type' => 'Thành phố'],
        ],
        'metadata' => ['total' => 1, 'page' => 1, 'limit' => 20],
    ];

    Http::fake([
        '*new-provinces*' => Http::sequence()
            ->push($payload, 200)
            ->push('', 500),
    ]);

    $service = app(LocationService::class);
    $service->getNewProvinces(null, null, null);

    $cacheKey = locationProvincesCacheKey();
    Cache::forget($cacheKey);

    $result = $service->getNewProvinces(null, null, null);

    expect($result)->toBe($payload);
    Http::assertSentCount(2);
});

test('locations clear-cache command bumps bust counter so stale keys are not reused', function (): void {
    $service = app(LocationService::class);
    $payload = [
        'success' => true,
        'data' => [
            ['code' => '79', 'name' => 'TP HCM', 'type' => 'Thành phố'],
        ],
    ];

    Http::fake([
        '*new-provinces*' => Http::response($payload, 200),
    ]);

    $service->getNewProvinces(null, null, null);

    Artisan::call('locations:clear-cache');

    Http::fake([
        '*new-provinces*' => Http::response('', 500),
    ]);

    try {
        $service->getNewProvinces(null, null, null);
        expect(false)->toBeTrue('Expected upstream failure after cache bust');
    } catch (Throwable) {
        expect(true)->toBeTrue();
    }

    Http::assertSentCount(1);
});

test('locations clear-cache command bumps bust counter', function (): void {
    Artisan::call('locations:clear-cache');

    expect((int) Cache::get('locations:cache_bust', 0))->toBeGreaterThan(0);
});
