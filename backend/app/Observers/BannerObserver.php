<?php

namespace App\Observers;

use App\Models\Banner;
use App\Services\Content\BannerCatalogService;
use App\Services\Content\BannerOrderingService;
use App\Services\Media\BannerImageStorageService;

class BannerObserver
{
    public function __construct(
        private BannerImageStorageService $bannerImageStorage,
        private BannerCatalogService $bannerCatalogService,
        private BannerOrderingService $bannerOrderingService,
    ) {}

    public function created(Banner $banner): void
    {
        $this->bannerCatalogService->forgetHomeBannersCache();
    }

    public function updated(Banner $banner): void
    {
        if ($banner->wasChanged('public_id')) {
            $this->bannerImageStorage->deleteByPublicId($banner->getOriginal('public_id'));
        }

        $this->bannerCatalogService->forgetHomeBannersCache();
    }

    public function deleting(Banner $banner): void
    {
        $this->bannerImageStorage->deleteByPublicId($banner->public_id);
    }

    public function deleted(Banner $banner): void
    {
        $this->bannerOrderingService->normalizeSortOrders();
        $this->bannerCatalogService->forgetHomeBannersCache();
    }
}
