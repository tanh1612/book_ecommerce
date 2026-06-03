<?php

namespace App\Services\Content;

use App\Models\Banner;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class BannerCatalogService
{
    public const HOME_BANNERS_CACHE_KEY = 'content:banners:home:v1';

    private const HOME_BANNERS_TTL_SECONDS = 900;

    /**
     * @return Collection<int, Banner>
     */
    public function homeBanners(): Collection
    {
        try {
            /** @var Collection<int, Banner> $banners */
            $banners = Cache::remember(
                self::HOME_BANNERS_CACHE_KEY,
                self::HOME_BANNERS_TTL_SECONDS,
                fn (): Collection => $this->queryHomeBanners(),
            );

            return $banners;
        } catch (Throwable $e) {
            Log::warning('Home banners cache read failed', [
                'key' => self::HOME_BANNERS_CACHE_KEY,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return $this->queryHomeBanners();
        }
    }

    public function forgetHomeBannersCache(): void
    {
        try {
            Cache::forget(self::HOME_BANNERS_CACHE_KEY);
        } catch (Throwable $e) {
            Log::warning('Home banners cache forget failed', [
                'key' => self::HOME_BANNERS_CACHE_KEY,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }
    }

    /**
     * @return Collection<int, Banner>
     */
    private function queryHomeBanners(): Collection
    {
        return Banner::query()->active()->ordered()->get();
    }
}
