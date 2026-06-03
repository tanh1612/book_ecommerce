<?php

namespace App\Services\Content;

use App\Models\Banner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class BannerOrderingService
{
    public function nextSortOrder(): int
    {
        $maxSortOrder = Banner::query()->max('sort_order');

        return ((int) ($maxSortOrder ?? 0)) + 1;
    }

    public function normalizeSortOrders(): void
    {
        try {
            DB::transaction(function (): void {
                $banners = Banner::query()
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get(['id', 'sort_order']);

                foreach ($banners as $index => $banner) {
                    $expected = $index + 1;

                    if ((int) $banner->sort_order !== $expected) {
                        Banner::query()->whereKey($banner->id)->update(['sort_order' => $expected]);
                    }
                }
            });
        } catch (Throwable $e) {
            Log::error('Normalize banner sort orders failed', [
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            throw $e;
        }
    }
}
