<?php

namespace App\Observers;

use App\Models\Promotion;
use App\Models\PromotionItem;
use App\Services\Catalog\CatalogCacheService;

class PromotionObserver
{
    public function __construct(
        private CatalogCacheService $catalogCache,
    ) {}

    public function saved(Promotion $promotion): void
    {
        $this->forgetBooksForPromotion($promotion);
    }

    public function deleted(Promotion $promotion): void
    {
        $this->forgetBooksForPromotion($promotion);
    }

    private function forgetBooksForPromotion(Promotion $promotion): void
    {
        $bookIds = PromotionItem::query()
            ->where('promotion_id', $promotion->id)
            ->pluck('book_id');

        $this->catalogCache->forgetBooksByIds($bookIds);
    }
}
