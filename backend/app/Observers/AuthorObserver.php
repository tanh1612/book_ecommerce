<?php

namespace App\Observers;

use App\Models\Author;
use App\Services\Ai\BookRagSyncDispatcher;
use App\Services\Catalog\CatalogCacheService;
use App\Services\Search\BookMeilisearchSyncDispatcher;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuthorObserver
{
    public function __construct(
        private CatalogCacheService $catalogCache,
        private BookMeilisearchSyncDispatcher $meilisearchSync,
        private BookRagSyncDispatcher $bookRagSync,
    ) {}

    public function saved(Author $author): void
    {
        $this->catalogCache->forgetBooksForAuthorAfterCommit((int) $author->id);

        if ($author->wasChanged('name')) {
            $bookIds = $author->books()->pluck('books.id');
            $this->meilisearchSync->dispatchMany($bookIds);
            $this->bookRagSync->dispatchMany($bookIds);
        }
    }

    public function deleting(Author $author): void
    {
        $this->catalogCache->forgetBooksForAuthorAfterCommit((int) $author->id);

        try {
            $bookIds = $author->books()->pluck('books.id');
            $this->meilisearchSync->dispatchMany($bookIds);
            $this->bookRagSync->dispatchMany($bookIds);
        } catch (Throwable $e) {
            Log::warning('Meilisearch reindex dispatch failed (author delete)', [
                'author_id' => $author->id,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }
    }
}
