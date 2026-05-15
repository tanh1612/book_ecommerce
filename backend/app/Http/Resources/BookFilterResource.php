<?php

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @property-read array{categories: Collection<int, Category>, publishers: Collection<int, \App\Models\Publisher>, suppliers: Collection<int, \App\Models\Supplier>, suggested_price_ranges: list<array{min: int, max: int|null, label: string}>} $resource
 */
class BookFilterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Collection<int, Category> $categories */
        $categories = $this->resource['categories'];

        return [
            'categories' => $categories->map(function (Category $category): array {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'children' => $category->children->map(fn (Category $child): array => [
                        'id' => $child->id,
                        'name' => $child->name,
                        'slug' => $child->slug,
                    ])->values(),
                ];
            })->values(),
            'publishers' => $this->resource['publishers']->map(fn (\App\Models\Publisher $publisher): array => [
                'id' => $publisher->id,
                'name' => $publisher->name,
            ])->values(),
            'suppliers' => $this->resource['suppliers']->map(fn (\App\Models\Supplier $supplier): array => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'slug' => $supplier->slug,
            ])->values(),
            'suggested_price_ranges' => $this->resource['suggested_price_ranges'],
        ];
    }
}
