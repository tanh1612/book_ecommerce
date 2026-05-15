<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    Http::preventStrayRequests();
});

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
});

test('locations clear-cache command bumps bust counter', function (): void {
    Artisan::call('locations:clear-cache');

    expect((int) Cache::get('locations:cache_bust', 0))->toBeGreaterThan(0);
});
