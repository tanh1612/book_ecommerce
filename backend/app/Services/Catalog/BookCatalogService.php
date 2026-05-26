<?php

namespace App\Services\Catalog;

use App\Models\Book;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class BookCatalogService
{
    public function __construct(
        private CatalogCacheService $catalogCache,
        private BookCatalogSearchService $bookCatalogSearch,
        private CatalogCategoryResolver $categoryResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateBooks(array $filters): LengthAwarePaginator
    {
        $keyword = isset($filters['keyword']) ? trim((string) $filters['keyword']) : '';

        if ($keyword !== '') {
            return $this->bookCatalogSearch->paginate($keyword, $filters);
        }

        return $this->paginateBooksFromDatabase($filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function paginateBooksFromDatabase(array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 40);
        $query = $this->baseListQuery();

        if (! empty($filters['category'])) {
            $categoryIds = $this->categoryResolver->descendantIdsForSlug((string) $filters['category']);

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

        if (! empty($filters['supplier'])) {
            $query->where('supplier_id', (int) $filters['supplier']);
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
        return $this->bookCatalogSearch->applyListQueryConstraints(Book::query());
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
                'categories:id,name,slug',
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
