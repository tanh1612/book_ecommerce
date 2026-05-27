<?php

namespace App\Observers;

use App\Models\Supplier;
use App\Services\Catalog\CatalogCacheService;

class SupplierObserver
{
    public function __construct(
        private CatalogCacheService $catalogCache,
    ) {}

    public function saved(Supplier $supplier): void
    {
        $this->catalogCache->forgetFiltersMetadataAfterCommit();
    }

    public function deleted(Supplier $supplier): void
    {
        $this->catalogCache->forgetFiltersMetadataAfterCommit();
    }
}
