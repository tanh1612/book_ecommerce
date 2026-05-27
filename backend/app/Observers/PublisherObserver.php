<?php

namespace App\Observers;

use App\Models\Publisher;
use App\Services\Catalog\CatalogCacheService;
use App\Services\Search\BookMeilisearchSyncDispatcher;
use Illuminate\Support\Facades\Log;
use Throwable;

class PublisherObserver
{
    public function __construct(
        private CatalogCacheService $catalogCache,
        private BookMeilisearchSyncDispatcher $meilisearchSync,
    ) {}

    public function saved(Publisher $publisher): void
    {
        $this->catalogCache->forgetFiltersMetadataAfterCommit();
        $this->catalogCache->forgetBooksByPublisherIdAfterCommit((int) $publisher->id);
    }

    public function deleting(Publisher $publisher): void
    {
        $bookIds = $publisher->books()->pluck('id');
        $this->catalogCache->forgetBooksByIdsAfterCommit($bookIds);

        try {
            $this->meilisearchSync->dispatchMany($bookIds);
        } catch (Throwable $e) {
            Log::warning('Meilisearch reindex dispatch failed (publisher delete)', [
                'publisher_id' => $publisher->id,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }
    }

    public function deleted(Publisher $publisher): void
    {
        $this->catalogCache->forgetFiltersMetadataAfterCommit();
    }
}
