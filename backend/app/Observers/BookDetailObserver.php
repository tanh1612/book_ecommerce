<?php

namespace App\Observers;

use App\Models\BookDetail;
use App\Services\Ai\BookRagSyncDispatcher;
use App\Services\Catalog\CatalogCacheService;
use App\Services\Search\BookMeilisearchSyncDispatcher;

class BookDetailObserver
{
    public function __construct(
        private CatalogCacheService $catalogCache,
        private BookMeilisearchSyncDispatcher $meilisearchSync,
        private BookRagSyncDispatcher $bookRagSync,
    ) {}

    public function saved(BookDetail $bookDetail): void
    {
        $this->catalogCache->forgetBookByIdAfterCommit((int) $bookDetail->book_id);
        $this->meilisearchSync->dispatch((int) $bookDetail->book_id);

        if ($bookDetail->wasRecentlyCreated || $bookDetail->wasChanged(['description', 'language', 'format', 'publication_year'])) {
            $this->bookRagSync->dispatch((int) $bookDetail->book_id);
        }
    }

    public function deleted(BookDetail $bookDetail): void
    {
        $this->catalogCache->forgetBookByIdAfterCommit((int) $bookDetail->book_id);
        $this->meilisearchSync->dispatch((int) $bookDetail->book_id);
        $this->bookRagSync->dispatch((int) $bookDetail->book_id);
    }
}
