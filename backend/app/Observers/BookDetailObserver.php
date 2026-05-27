<?php

namespace App\Observers;

use App\Models\BookDetail;
use App\Services\Catalog\CatalogCacheService;
use App\Services\Search\BookMeilisearchSyncDispatcher;

class BookDetailObserver
{
    public function __construct(
        private CatalogCacheService $catalogCache,
        private BookMeilisearchSyncDispatcher $meilisearchSync,
    ) {}

    public function saved(BookDetail $bookDetail): void
    {
        $this->catalogCache->forgetBookByIdAfterCommit((int) $bookDetail->book_id);
        $this->meilisearchSync->dispatch((int) $bookDetail->book_id);
    }

    public function deleted(BookDetail $bookDetail): void
    {
        $this->catalogCache->forgetBookByIdAfterCommit((int) $bookDetail->book_id);
        $this->meilisearchSync->dispatch((int) $bookDetail->book_id);
    }
}
