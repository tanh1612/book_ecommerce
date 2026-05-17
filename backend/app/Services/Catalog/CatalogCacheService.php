<?php

namespace App\Services\Catalog;

use App\Models\Book;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Catalog read-through cache. Invalidation is wired from {@see \App\Providers\AppServiceProvider::boot()}
 * model observers and {@see \App\Models\BookCategory} / {@see \App\Models\BookAuthor} pivot hooks — avoid raw DB writes that bypass them.
 */
class CatalogCacheService
{
    private const BOOK_DETAIL_TTL_SECONDS = 900;

    private const FILTERS_METADATA_TTL_SECONDS = 7200;

    public function bookDetailCacheKey(string $slug): string
    {
        return 'catalog:book:slug:'.trim($slug);
    }

    public function filtersMetadataCacheKey(): string
    {
        return 'catalog:filters:v1';
    }

    /**
     * @param  callable(): Book  $resolver
     */
    public function rememberBookDetail(string $slug, callable $resolver): Book
    {
        $slug = trim($slug);
        if ($slug === '') {
            return $resolver();
        }

        $key = $this->bookDetailCacheKey($slug);

        try {
            /** @var Book $book */
            $book = Cache::remember($key, self::BOOK_DETAIL_TTL_SECONDS, $resolver);

            return $book;
        } catch (Throwable $e) {
            Log::warning('Catalog book detail cache read failed', [
                'key' => $key,
                'slug' => $slug,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return $resolver();
        }
    }

    /**
     * @param  callable(): array<string, mixed>  $resolver
     * @return array<string, mixed>
     */
    public function rememberFiltersMetadata(callable $resolver): array
    {
        $key = $this->filtersMetadataCacheKey();

        try {
            return Cache::remember($key, self::FILTERS_METADATA_TTL_SECONDS, $resolver);
        } catch (Throwable $e) {
            Log::warning('Catalog filters metadata cache read failed', [
                'key' => $key,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return $resolver();
        }
    }

    public function forgetBookBySlug(?string $slug): void
    {
        if ($slug === null || trim($slug) === '') {
            return;
        }

        $key = $this->bookDetailCacheKey($slug);

        try {
            Cache::forget($key);
        } catch (Throwable $e) {
            Log::warning('Catalog book detail cache forget failed', [
                'key' => $key,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }
    }

    public function forgetBookById(int $bookId): void
    {
        $this->forgetBooksByIds([$bookId]);
    }

    /**
     * @param  iterable<int|string>  $bookIds
     */
    public function forgetBooksByIds(iterable $bookIds): void
    {
        $ids = [];
        foreach ($bookIds as $rawId) {
            $id = (int) $rawId;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return;
        }

        try {
            $slugs = Book::query()->whereIn('id', $ids)->pluck('slug');
            foreach ($slugs as $slug) {
                if (is_string($slug) && $slug !== '') {
                    $this->forgetBookBySlug($slug);
                }
            }
        } catch (Throwable $e) {
            Log::warning('Catalog book detail cache batch forget failed', [
                'book_ids_count' => count($ids),
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }
    }

    public function forgetFiltersMetadata(): void
    {
        $key = $this->filtersMetadataCacheKey();

        try {
            Cache::forget($key);
        } catch (Throwable $e) {
            Log::warning('Catalog filters metadata cache forget failed', [
                'key' => $key,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }
    }

    public function forgetBooksByPublisherId(int $publisherId): void
    {
        if ($publisherId <= 0) {
            return;
        }

        try {
            Book::query()->where('publisher_id', $publisherId)->pluck('slug')->each(function (mixed $slug): void {
                if (is_string($slug) && $slug !== '') {
                    $this->forgetBookBySlug($slug);
                }
            });
        } catch (Throwable $e) {
            Log::warning('Catalog cache forget by publisher failed', [
                'publisher_id' => $publisherId,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }
    }

    public function forgetBooksForAuthor(int $authorId): void
    {
        if ($authorId <= 0) {
            return;
        }

        try {
            $bookIds = DB::table('book_authors')->where('author_id', $authorId)->pluck('book_id');
            $this->forgetBooksByIds($bookIds);
        } catch (Throwable $e) {
            Log::warning('Catalog cache forget by author failed', [
                'author_id' => $authorId,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }
    }

    /**
     * @param  list<int>  $categoryIds
     */
    public function forgetBooksLinkedToCategories(array $categoryIds): void
    {
        $categoryIds = array_values(array_unique(array_filter($categoryIds)));
        if ($categoryIds === []) {
            return;
        }

        try {
            $bookIds = DB::table('book_categories')->whereIn('category_id', $categoryIds)->distinct()->pluck('book_id');
            $this->forgetBooksByIds($bookIds);
        } catch (Throwable $e) {
            Log::warning('Catalog cache forget by categories failed', [
                'category_ids' => $categoryIds,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }
    }
}
