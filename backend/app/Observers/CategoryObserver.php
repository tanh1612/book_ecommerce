<?php

namespace App\Observers;

use App\Models\Category;
use App\Services\Catalog\CatalogCacheService;
use Illuminate\Support\Facades\Log;
use Throwable;

class CategoryObserver
{
    public function __construct(
        private CatalogCacheService $catalogCache,
    ) {}

    public function saved(Category $category): void
    {
        $this->invalidateBranchBooks($category);
        $this->catalogCache->forgetFiltersMetadata();
    }

    public function deleting(Category $category): void
    {
        $this->invalidateBranchBooks($category);
        $this->catalogCache->forgetFiltersMetadata();
    }

    private function invalidateBranchBooks(Category $category): void
    {
        try {
            $categoryIds = array_merge([(int) $category->id], $category->getDescendantIds());
            $this->catalogCache->forgetBooksLinkedToCategories($categoryIds);
        } catch (Throwable $e) {
            Log::warning('Catalog cache invalidation failed (category branch)', [
                'category_id' => $category->id,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }
    }
}
