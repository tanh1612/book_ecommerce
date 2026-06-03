<?php

use App\Models\Banner;
use App\Services\Content\BannerOrderingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createBannerWithoutEvents(array $attributes = []): Banner
{
    return Banner::withoutEvents(fn (): Banner => Banner::factory()->create($attributes));
}

test('banner ordering service returns next sort order', function (): void {
    $service = app(BannerOrderingService::class);

    expect($service->nextSortOrder())->toBe(1);

    createBannerWithoutEvents(['sort_order' => 2]);
    createBannerWithoutEvents(['sort_order' => 7]);

    expect($service->nextSortOrder())->toBe(8);
});

test('banner ordering service normalizes sort orders', function (): void {
    $third = createBannerWithoutEvents(['sort_order' => 20]);
    $first = createBannerWithoutEvents(['sort_order' => 5]);
    $second = createBannerWithoutEvents(['sort_order' => 5]);

    app(BannerOrderingService::class)->normalizeSortOrders();

    expect($first->refresh()->sort_order)->toBe(1)
        ->and($second->refresh()->sort_order)->toBe(2)
        ->and($third->refresh()->sort_order)->toBe(3);
});
