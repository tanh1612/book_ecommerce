<?php

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\Publisher;
use App\Models\Supplier;

class BookFilterService
{
    public function __construct(
        private CatalogCacheService $catalogCache,
    ) {}

    /**
     * Metadata cho UI bộ lọc; không thay thế logic lọc trong {@see BookCatalogService::paginateBooks()}.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->catalogCache->rememberFiltersMetadata(function (): array {
            $categories = Category::query()
                ->whereNull('parent_id')
                ->where('is_active', true)
                ->with([
                    'children' => function ($query): void {
                        $query->where('is_active', true)
                            ->orderBy('name');
                    },
                ])
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'parent_id', 'is_active']);

            $publishers = Publisher::query()
                ->whereHas('books', fn ($q) => $q->active())
                ->orderBy('name')
                ->get(['id', 'name']);

            $suppliers = Supplier::query()
                ->whereHas('books', fn ($q) => $q->active())
                ->orderBy('name')
                ->get(['id', 'name', 'slug']);

            return [
                'categories' => $categories,
                'publishers' => $publishers,
                'suppliers' => $suppliers,
                'suggested_price_ranges' => $this->suggestedPriceRanges(),
            ];
        });
    }

    /**
     * @return list<array{min: int, max: int|null, label: string}>
     */
    private function suggestedPriceRanges(): array
    {
        return [
            ['min' => 0, 'max' => 50_000, 'label' => 'Dưới 50k'],
            ['min' => 50_000, 'max' => 100_000, 'label' => '50k – 100k'],
            ['min' => 100_000, 'max' => 200_000, 'label' => '100k – 200k'],
            ['min' => 200_000, 'max' => 500_000, 'label' => '200k – 500k'],
            ['min' => 500_000, 'max' => null, 'label' => 'Trên 500k'],
        ];
    }
}
