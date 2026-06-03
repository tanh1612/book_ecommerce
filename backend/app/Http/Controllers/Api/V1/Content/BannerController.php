<?php

namespace App\Http\Controllers\Api\V1\Content;

use App\Http\Controllers\Controller;
use App\Http\Resources\BannerResource;
use App\Services\Content\BannerCatalogService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BannerController extends Controller
{
    public function index(BannerCatalogService $bannerCatalogService): AnonymousResourceCollection
    {
        return BannerResource::collection($bannerCatalogService->homeBanners());
    }
}
