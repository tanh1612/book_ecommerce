<?php

namespace App\Support\Catalog;

use App\Models\Category;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;

class CategoryBreadcrumbIndex
{
    /** @var array<string, list<int>> */
    private array $normalizedBreadcrumbToCategoryIds = [];

    /** @var array<string, list<int>> */
    private array $normalizedNameToCategoryIds = [];

    public static function buildFromDatabase(): self
    {
        $index = new self;

        foreach (Category::with(['parent.parent'])->get() as $category) {
            $breadcrumbKey = CategoryBreadcrumbNormalizer::normalize($category->getBreadcrumb());
            $nameKey = CategoryBreadcrumbNormalizer::normalize($category->name);

            $index->normalizedBreadcrumbToCategoryIds[$breadcrumbKey][] = $category->id;
            $index->normalizedNameToCategoryIds[$nameKey][] = $category->id;
        }

        return $index;
    }

    public function resolveCategoryId(string $csvBreadcrumb): int
    {
        $key = CategoryBreadcrumbNormalizer::normalize($csvBreadcrumb);
        $breadcrumbIds = $this->normalizedBreadcrumbToCategoryIds[$key] ?? [];

        if ($breadcrumbIds !== []) {
            return $this->resolveUniqueCategoryId(
                $breadcrumbIds,
                "Danh mục '{$csvBreadcrumb}' không xác định được (trùng khóa sau chuẩn hóa trong hệ thống).",
            );
        }

        $nameIds = $this->normalizedNameToCategoryIds[$key] ?? [];

        if ($nameIds === []) {
            throw new RowImportFailedException("Danh mục '{$csvBreadcrumb}' không tồn tại.");
        }

        return $this->resolveUniqueCategoryId(
            $nameIds,
            "Danh mục '{$csvBreadcrumb}' trùng tên trong nhiều nhánh. Vui lòng dùng breadcrumb đầy đủ, ví dụ: 'Danh mục cha > {$csvBreadcrumb}'.",
        );
    }

    /**
     * @param  list<int>  $ids
     */
    private function resolveUniqueCategoryId(array $ids, string $ambiguousMessage): int
    {
        $uniqueIds = array_values(array_unique($ids));

        if (count($uniqueIds) > 1) {
            throw new RowImportFailedException($ambiguousMessage);
        }

        return $uniqueIds[0];
    }
}
