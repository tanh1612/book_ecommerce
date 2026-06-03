<?php

use App\Models\Banner;
use App\Services\Content\BannerCatalogService;
use App\Services\Media\BannerImageStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $storage = Mockery::mock(BannerImageStorageService::class);
    $storage->shouldReceive('deliveryUrlFromPublicId')
        ->andReturnUsing(fn (string $publicId): string => 'https://res.cloudinary.com/test/image/upload/'.$publicId.'.jpg');
    $storage->shouldReceive('deleteByPublicId')->andReturnNull();

    app()->instance(BannerImageStorageService::class, $storage);
});

test('banner observer deletes previous cloudinary asset when public id changes', function (): void {
    $storage = Mockery::mock(BannerImageStorageService::class);
    $storage->shouldReceive('deleteByPublicId')
        ->once()
        ->with('book_ecommerce/banners/home/old-banner');
    $storage->shouldReceive('deliveryUrlFromPublicId')
        ->andReturn('https://res.cloudinary.com/test/image/upload/new-banner.jpg');

    app()->instance(BannerImageStorageService::class, $storage);

    $banner = Banner::withoutEvents(fn (): Banner => Banner::query()->create([
        'title' => 'Swap image',
        'public_id' => 'book_ecommerce/banners/home/old-banner',
        'image_url' => 'https://res.cloudinary.com/test/image/upload/old-banner.jpg',
        'sort_order' => 0,
        'is_active' => true,
    ]));

    $banner->update([
        'public_id' => 'book_ecommerce/banners/home/new-banner',
    ]);
});

test('banner observer does not delete cloudinary asset when only non image fields change', function (): void {
    $storage = Mockery::mock(BannerImageStorageService::class);
    $storage->shouldReceive('deleteByPublicId')->never();
    $storage->shouldReceive('deliveryUrlFromPublicId')->never();

    app()->instance(BannerImageStorageService::class, $storage);

    $banner = Banner::withoutEvents(fn (): Banner => Banner::query()->create([
        'title' => 'Title only',
        'public_id' => 'book_ecommerce/banners/home/stable-banner',
        'image_url' => 'https://res.cloudinary.com/test/image/upload/stable-banner.jpg',
        'sort_order' => 0,
        'is_active' => true,
    ]));

    $banner->update(['title' => 'Updated title']);
});

test('banner observer clears home banner cache when banner is updated', function (): void {
    Cache::put(BannerCatalogService::HOME_BANNERS_CACHE_KEY, collect(), 900);

    $banner = Banner::withoutEvents(fn (): Banner => Banner::factory()->create());

    expect(Cache::has(BannerCatalogService::HOME_BANNERS_CACHE_KEY))->toBeTrue();

    $banner->update(['sort_order' => 2]);

    expect(Cache::has(BannerCatalogService::HOME_BANNERS_CACHE_KEY))->toBeFalse();
});

test('banner observer clears home banner cache when banner is created', function (): void {
    Cache::put(BannerCatalogService::HOME_BANNERS_CACHE_KEY, collect(), 900);

    Banner::factory()->create();

    expect(Cache::has(BannerCatalogService::HOME_BANNERS_CACHE_KEY))->toBeFalse();
});

test('banner observer clears home banner cache when banner is deleted', function (): void {
    $banner = Banner::withoutEvents(fn (): Banner => Banner::factory()->create());

    Cache::put(BannerCatalogService::HOME_BANNERS_CACHE_KEY, collect(), 900);

    $banner->delete();

    expect(Cache::has(BannerCatalogService::HOME_BANNERS_CACHE_KEY))->toBeFalse();
});

test('banner observer normalizes sort orders when banner is deleted', function (): void {
    $first = Banner::withoutEvents(fn (): Banner => Banner::factory()->create(['sort_order' => 1]));
    $deleted = Banner::withoutEvents(fn (): Banner => Banner::factory()->create(['sort_order' => 2]));
    $third = Banner::withoutEvents(fn (): Banner => Banner::factory()->create(['sort_order' => 3]));

    $deleted->delete();

    expect($first->refresh()->sort_order)->toBe(1)
        ->and($third->refresh()->sort_order)->toBe(2);
});
