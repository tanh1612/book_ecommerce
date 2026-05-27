<?php

namespace App\Models;

use App\Services\Catalog\CatalogCacheService;
use App\Services\Search\BookMeilisearchSyncDispatcher;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\Log;
use Throwable;

class BookCategory extends Pivot
{
    protected $table = 'book_categories';

    public $incrementing = false;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::saved(function (BookCategory $pivot): void {
            self::onPivotChanged((int) $pivot->book_id);
        });

        static::deleted(function (BookCategory $pivot): void {
            self::onPivotChanged((int) $pivot->book_id);
        });
    }

    private static function onPivotChanged(int $bookId): void
    {
        if ($bookId <= 0) {
            return;
        }

        app(CatalogCacheService::class)->forgetBookByIdAfterCommit($bookId);

        try {
            app(BookMeilisearchSyncDispatcher::class)->dispatch($bookId);
        } catch (Throwable $e) {
            Log::warning('Book category search sync dispatch failed', [
                'book_id' => $bookId,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }
    }
}
