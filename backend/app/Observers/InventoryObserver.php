<?php

namespace App\Observers;

use App\Models\Inventory;
use App\Services\Catalog\CatalogCacheService;

class InventoryObserver
{
    public function __construct(
        private CatalogCacheService $catalogCache,
    ) {}

    public function saved(Inventory $inventory): void
    {
        $this->catalogCache->forgetBookById((int) $inventory->book_id);
    }

    public function deleted(Inventory $inventory): void
    {
        $this->catalogCache->forgetBookById((int) $inventory->book_id);
    }
}
