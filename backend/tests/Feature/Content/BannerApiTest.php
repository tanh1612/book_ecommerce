<?php

use App\Models\Banner;
use App\Services\Content\BannerCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

function createTestBanner(array $attributes = []): Banner
{
    return Banner::withoutEvents(fn (): Banner => Banner::factory()->create($attributes));
}

test('banners index returns only active banners', function (): void {
    $active = createTestBanner(['title' => 'Active banner', 'sort_order' => 0]);
    createTestBanner(['title' => 'Hidden banner', 'is_active' => false, 'sort_order' => 1]);

    $response = $this->getJson('/api/v1/banners');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $active->id)
        ->assertJsonPath('data.0.title', 'Active banner');
});

test('banners index returns empty data when no active banners', function (): void {
    createTestBanner(['is_active' => false]);

    $this->getJson('/api/v1/banners')
        ->assertOk()
        ->assertExactJson(['data' => []]);
});

test('banners index sorts by sort_order then id', function (): void {
    $second = createTestBanner(['title' => 'Second', 'sort_order' => 1]);
    $first = createTestBanner(['title' => 'First', 'sort_order' => 0]);
    $third = createTestBanner(['title' => 'Third', 'sort_order' => 0]);

    $ids = collect($this->getJson('/api/v1/banners')->json('data'))->pluck('id')->all();

    expect($ids)->toBe([$first->id, $third->id, $second->id]);
});

test('banners index does not expose internal fields', function (): void {
    createTestBanner([
        'title' => 'Public fields only',
        'sort_order' => 5,
        'public_id' => 'book_ecommerce/banners/home/secret-id',
    ]);

    $row = $this->getJson('/api/v1/banners')->json('data.0');

    expect($row)->toHaveKeys(['id', 'title', 'image_url'])
        ->and($row)->not->toHaveKeys([
            'public_id',
            'sort_order',
            'is_active',
            'created_at',
            'updated_at',
        ]);
});

test('home banners cache is cleared when banner visibility changes', function (): void {
    $banner = createTestBanner(['title' => 'Cached', 'sort_order' => 0, 'is_active' => true]);

    $this->getJson('/api/v1/banners')->assertOk();
    expect(Cache::has(BannerCatalogService::HOME_BANNERS_CACHE_KEY))->toBeTrue();

    $banner->update(['is_active' => false]);

    expect(Cache::has(BannerCatalogService::HOME_BANNERS_CACHE_KEY))->toBeFalse();

    $this->getJson('/api/v1/banners')
        ->assertOk()
        ->assertExactJson(['data' => []]);
});

test('home banners cache is cleared when banner sort order changes', function (): void {
    $banner = createTestBanner(['title' => 'Reorder', 'sort_order' => 0]);

    $this->getJson('/api/v1/banners')->assertOk();
    expect(Cache::has(BannerCatalogService::HOME_BANNERS_CACHE_KEY))->toBeTrue();

    $banner->update(['sort_order' => 10]);

    expect(Cache::has(BannerCatalogService::HOME_BANNERS_CACHE_KEY))->toBeFalse();
});
