<?php

namespace App\Services\Catalog;

use App\Models\Book;
use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class BookCatalogService
{
    public function __construct(
        private CatalogCacheService $catalogCache,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateBooks(array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 40);
        $query = $this->baseListQuery();

        if (! empty($filters['category'])) {
            $category = Category::query()
                ->where('slug', (string) $filters['category'])
                ->where('is_active', true)
                ->firstOrFail();

            $categoryIds = array_values(array_unique(array_merge(
                [$category->id],
                $category->getDescendantIds()
            )));

            $query->whereHas('categories', function (Builder $relation) use ($categoryIds): void {
                $relation->whereIn('categories.id', $categoryIds);
            });
        }

        if (isset($filters['price_min'])) {
            $query->where('selling_price', '>=', (string) $filters['price_min']);
        }

        if (isset($filters['price_max'])) {
            $query->where('selling_price', '<=', (string) $filters['price_max']);
        }

        if (! empty($filters['publisher'])) {
            $query->where('publisher_id', (int) $filters['publisher']);
        }

        if (array_key_exists('supplier', $filters) && $filters['supplier'] !== null && $filters['supplier'] !== '') {
            $supplier = $filters['supplier'];
            if (is_numeric($supplier)) {
                $query->where('supplier_id', (int) $supplier);
            } else {
                $query->whereHas('supplier', function (Builder $relation) use ($supplier): void {
                    $relation->where('slug', (string) $supplier);
                });
            }
        }

        $sort = (string) ($filters['sort'] ?? 'newest');
        match ($sort) {
            'price_asc' => $query->orderBy('selling_price')->orderBy('id'),
            'price_desc' => $query->orderByDesc('selling_price')->orderByDesc('id'),
            'rating_desc' => $query->orderByDesc('average_rating')->orderByDesc('review_count')->orderByDesc('id'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };

        return $query->paginate($perPage)->withQueryString();
    }

    public function getBookBySlug(string $slug): Book
    {
        $slug = trim($slug);
        if ($slug === '') {
            abort(404);
        }

        return $this->catalogCache->rememberBookDetail($slug, function () use ($slug): Book {
            return $this->baseDetailQuery()
                ->where('slug', $slug)
                ->firstOrFail();
        });
    }

    /**
     * @return Builder<Book>
     */
    private function baseListQuery(): Builder
    {
        return Book::query()
            ->with([
                'authors',
                'categories' => function ($query): void {
                    $query->where('categories.is_active', true);
                },
                'publisher:id,name',
                'images' => function ($query): void {
                    $query->select(['id', 'book_id', 'image_url', 'sort_order'])
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

    /**
     * @return Builder<Book>
     */
    private function baseDetailQuery(): Builder
    {
        return Book::query()
            ->with([
                'detail',
                'images' => function ($query): void {
                    $query->select(['id', 'book_id', 'public_id', 'image_url', 'sort_order'])
                        ->orderBy('sort_order')
                        ->orderBy('id');
                },
                'authors:id,name',
                'categories' => function ($query): void {
                    $query->where('categories.is_active', true);
                },
                'publisher:id,name',
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
            ]);
    }
}
