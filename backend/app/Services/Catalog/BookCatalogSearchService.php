<?php

namespace App\Services\Catalog;

use App\Models\Book;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Scout\Builder as ScoutBuilder;

class BookCatalogSearchService
{
    public function __construct(
        private CatalogCategoryResolver $categoryResolver,
    ) {}

    /**
     * @return Collection<int, Book>
     */
    public function suggestions(string $keyword, int $limit): Collection
    {
        return Book::search($keyword)
            ->take($limit)
            ->query(fn (Builder $query): Builder => $query
                ->select(['id', 'name', 'slug', 'thumbnail'])
                ->with([
                    'images' => function ($imageQuery): void {
                        $imageQuery->select(['id', 'book_id', 'image_url', 'sort_order'])
                            ->orderBy('sort_order')
                            ->orderBy('id')
                            ->limit(1);
                    },
                ]))
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(string $keyword, array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 40);
        $sort = (string) ($filters['sort'] ?? 'relevance');

        $builder = Book::search($keyword);
        $this->applyCatalogFilters($builder, $filters);
        $this->applyCatalogSort($builder, $sort);

        return $builder
            ->query(fn (Builder $query): Builder => $this->applyListQueryConstraints($query))
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function applyCatalogFilters(ScoutBuilder $builder, array $filters): void
    {
        if (! empty($filters['category'])) {
            $categoryIds = $this->categoryResolver->descendantIdsForSlug((string) $filters['category']);
            $builder->whereIn('category_ids', $categoryIds);
        }

        if (isset($filters['price_min'])) {
            $builder->where('selling_price', '>=', (float) $filters['price_min']);
        }

        if (isset($filters['price_max'])) {
            $builder->where('selling_price', '<=', (float) $filters['price_max']);
        }

        if (! empty($filters['publisher'])) {
            $builder->where('publisher_id', (int) $filters['publisher']);
        }

        if (! empty($filters['supplier'])) {
            $builder->where('supplier_id', (int) $filters['supplier']);
        }
    }

    public function applyCatalogSort(ScoutBuilder $builder, string $sort): void
    {
        match ($sort) {
            'newest' => $builder->orderByDesc('created_at')->orderByDesc('id'),
            'price_asc' => $builder->orderBy('selling_price')->orderBy('id'),
            'price_desc' => $builder->orderByDesc('selling_price')->orderByDesc('id'),
            'rating_desc' => $builder
                ->orderByDesc('average_rating')
                ->orderByDesc('review_count')
                ->orderByDesc('id'),
            default => null,
        };
    }

    /**
     * @param  Builder<Book>  $query
     * @return Builder<Book>
     */
    public function applyListQueryConstraints(Builder $query): Builder
    {
        return $query
            ->with([
                'authors',
                'categories:id,name,slug',
                'publisher:id,name',
                'images' => function ($imageQuery): void {
                    $imageQuery->select(['id', 'book_id', 'image_url', 'sort_order'])
                        ->orderBy('sort_order')
                        ->orderBy('id')
                        ->limit(1);
                },
                'inventories:id,book_id,quantity,reserved_quantity',
            ])
            ->select([
                'id',
                'publisher_id',
                'name',
                'slug',
                'thumbnail',
                'original_price',
                'selling_price',
                'review_count',
                'average_rating',
                'is_active',
                'created_at',
            ]);
    }
}
