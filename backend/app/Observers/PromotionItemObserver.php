<?php

namespace App\Observers;

use App\Models\PromotionItem;
use App\Services\Catalog\CatalogCacheService;

class PromotionItemObserver
{
    public function __construct(
        private CatalogCacheService $catalogCache,
    ) {}

    public function saved(PromotionItem $promotionItem): void
    {
        $this->catalogCache->forgetBookById((int) $promotionItem->book_id);
    }

    public function deleted(PromotionItem $promotionItem): void
    {
        $this->catalogCache->forgetBookById((int) $promotionItem->book_id);
    }
}
