<?php

namespace App\Observers;

use App\Models\Author;
use App\Services\Catalog\CatalogCacheService;
use App\Services\Search\BookMeilisearchSyncDispatcher;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuthorObserver
{
    public function __construct(
        private CatalogCacheService $catalogCache,
        private BookMeilisearchSyncDispatcher $meilisearchSync,
    ) {}

    public function saved(Author $author): void
    {
        $this->catalogCache->forgetBooksForAuthorAfterCommit((int) $author->id);

        if ($author->wasChanged('name')) {
            $this->meilisearchSync->dispatchMany(
                $author->books()->pluck('books.id')
            );
        }
    }

    public function deleting(Author $author): void
    {
        $this->catalogCache->forgetBooksForAuthorAfterCommit((int) $author->id);

        try {
            $this->meilisearchSync->dispatchMany(
                $author->books()->pluck('books.id')
            );
        } catch (Throwable $e) {
            Log::warning('Meilisearch reindex dispatch failed (author delete)', [
                'author_id' => $author->id,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }
    }
}
