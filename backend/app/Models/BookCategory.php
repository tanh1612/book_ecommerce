<?php

namespace App\Models;

use App\Services\Catalog\CatalogCacheService;
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
            self::forgetBookCache((int) $pivot->book_id);
        });

        static::deleted(function (BookCategory $pivot): void {
            self::forgetBookCache((int) $pivot->book_id);
        });
    }

    private static function forgetBookCache(int $bookId): void
    {
        if ($bookId <= 0) {
            return;
        }

        try {
            app(CatalogCacheService::class)->forgetBookById($bookId);
        } catch (Throwable $e) {
            Log::warning('Catalog cache invalidation failed (book_categories)', [
                'book_id' => $bookId,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }
    }
}
