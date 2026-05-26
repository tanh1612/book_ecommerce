<?php

namespace App\Services\Catalog;

use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class CategoryDeletionService
{
    public function delete(Category $category): bool
    {
        try {
            return DB::transaction(function () use ($category): bool {
                $lockedCategory = Category::query()
                    ->whereKey($category->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                return (bool) $lockedCategory->delete();
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Category delete failed', [
                'category_id' => $category->getKey(),
                'error' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            throw $exception;
        }
    }

    /**
     * @param  iterable<Category>  $categories
     */
    public function deleteMany(iterable $categories): void
    {
        $categoryIds = collect($categories)
            ->map(static fn (Category $category): int => (int) $category->getKey())
            ->filter(static fn (int $categoryId): bool => $categoryId > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($categoryIds === []) {
            return;
        }

        try {
            DB::transaction(function () use ($categoryIds): void {
                $lockedCategories = Category::query()
                    ->whereKey($categoryIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                foreach ($lockedCategories as $category) {
                    $category->delete();
                }
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Bulk category delete failed', [
                'category_ids' => $categoryIds,
                'error' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            throw $exception;
        }
    }
}
