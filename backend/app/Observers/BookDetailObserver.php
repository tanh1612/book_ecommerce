<?php

namespace App\Observers;

use App\Models\BookDetail;
use App\Services\Catalog\CatalogCacheService;

class BookDetailObserver
{
    public function __construct(
        private CatalogCacheService $catalogCache,
    ) {}

    public function saved(BookDetail $bookDetail): void
    {
        $this->catalogCache->forgetBookById((int) $bookDetail->book_id);
    }
}
