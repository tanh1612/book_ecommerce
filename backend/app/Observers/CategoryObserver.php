<?php

namespace App\Observers;

use App\Models\Category;
use App\Services\Catalog\CatalogCacheService;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class CategoryObserver
{
    public function __construct(
        private CatalogCacheService $catalogCache,
    ) {}

    /**
     * Prevent enabling a category while any ancestor is inactive (admin cannot bypass via direct save).
     */
    public function saving(Category $category): void
    {
        if (! $category->is_active) {
            return;
        }

        $parentId = $category->parent_id;

        while ($parentId !== null) {
            /** @var Category|null $parentRow */
            $parentRow = Category::query()
                ->whereKey($parentId)
                ->first(['id', 'parent_id', 'is_active']);

            if ($parentRow === null || ! $parentRow->is_active) {
                throw ValidationException::withMessages([
                    'is_active' => 'Không cho phép bật danh mục này vì danh mục cha đang tắt.',
                ]);
            }

            $parentId = $parentRow->parent_id;
        }
    }

    public function saved(Category $category): void
    {
        if ($category->wasChanged('is_active')) {
            $this->syncDescendantActiveStates($category);
        }

        $this->invalidateBranchBooks($category);
        $this->catalogCache->forgetFiltersMetadata();
    }

    public function deleting(Category $category): void
    {
        $this->invalidateBranchBooks($category);
        $this->catalogCache->forgetFiltersMetadata();
    }

    private function syncDescendantActiveStates(Category $category): void
    {
        $descendantIds = $category->getDescendantIds();

        if ($descendantIds === []) {
            return;
        }

        try {
            Category::withoutEvents(function () use ($descendantIds, $category): void {
                Category::query()->whereIn('id', $descendantIds)->update([
                    'is_active' => (bool) $category->is_active,
                ]);
            });
        } catch (Throwable $e) {
            Log::error('Category descendant is_active sync failed', [
                'category_id' => $category->id,
                'is_active' => $category->is_active,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            throw $e;
        }
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
