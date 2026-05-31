<?php

namespace App\Models;

use App\Services\Ai\BookRagSyncDispatcher;
use App\Services\Catalog\CatalogCacheService;
use App\Services\Search\BookMeilisearchSyncDispatcher;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\Log;
use Throwable;

class BookAuthor extends Pivot
{
    protected $table = 'book_authors';

    public $incrementing = false;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::saved(function (BookAuthor $pivot): void {
            self::onPivotChanged((int) $pivot->book_id);
        });

        static::deleted(function (BookAuthor $pivot): void {
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
            app(BookRagSyncDispatcher::class)->dispatch($bookId);
        } catch (Throwable $e) {
            Log::warning('Book author pivot side effects failed', [
                'book_id' => $bookId,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }
    }
}
