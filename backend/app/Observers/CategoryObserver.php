<?php

namespace App\Observers;

use App\Models\Category;
use App\Services\Catalog\CatalogCacheService;
use App\Services\Search\BookMeilisearchSyncDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class CategoryObserver
{
    public function __construct(
        private CatalogCacheService $catalogCache,
        private BookMeilisearchSyncDispatcher $meilisearchSync,
    ) {}

    public function saved(Category $category): void
    {
        $this->invalidateBranchBooks($category);
        $this->catalogCache->forgetFiltersMetadata();
    }

    public function deleting(Category $category): void
    {
        if ($category->children()->exists()) {
            throw ValidationException::withMessages([
                'name' => 'Không thể xóa danh mục đang có danh mục con. Hãy xóa hoặc chuyển các danh mục con trước.',
            ]);
        }

        if ($category->books()->exists()) {
            throw ValidationException::withMessages([
                'name' => 'Không thể xóa danh mục đang liên kết với sách. Hãy gán sách sang danh mục khác trước.',
            ]);
        }

        try {
            $bookIds = DB::table('book_categories')
                ->where('category_id', $category->id)
                ->distinct()
                ->pluck('book_id');

            $this->meilisearchSync->dispatchMany($bookIds);
        } catch (Throwable $e) {
            Log::warning('Meilisearch reindex dispatch failed (category delete)', [
                'category_id' => $category->id,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }

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
