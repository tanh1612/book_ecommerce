<?php

namespace App\Observers;

use App\Models\Publisher;
use App\Services\Catalog\CatalogCacheService;

class PublisherObserver
{
    public function __construct(
        private CatalogCacheService $catalogCache,
    ) {}

    public function saved(Publisher $publisher): void
    {
        $this->catalogCache->forgetFiltersMetadata();
        $this->catalogCache->forgetBooksByPublisherId((int) $publisher->id);
    }

    public function deleted(Publisher $publisher): void
    {
        $this->catalogCache->forgetFiltersMetadata();
    }
}
