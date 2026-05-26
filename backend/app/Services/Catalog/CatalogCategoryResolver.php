<?php

namespace App\Services\Catalog;

use App\Models\Category;

class CatalogCategoryResolver
{
    /**
     * @return list<int>
     */
    public function descendantIdsForSlug(string $slug): array
    {
        $category = Category::query()
            ->where('slug', $slug)
            ->firstOrFail();

        return array_values(array_unique(array_merge(
            [$category->id],
            $category->getDescendantIds()
        )));
    }
}
