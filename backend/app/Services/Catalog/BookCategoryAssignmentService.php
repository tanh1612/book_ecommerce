<?php

namespace App\Services\Catalog;

use App\Models\Book;
use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class BookCategoryAssignmentService
{
    public const VALIDATION_KEY = 'categories';

    public const MIN_CATEGORIES_MESSAGE = 'Sách phải thuộc ít nhất một danh mục. Hãy gán danh mục khác trước khi tháo.';

    /**
     * @param  array<int|string>  $categoryIds
     */
    public function syncCategories(Book $book, array $categoryIds): void
    {
        $categoryIds = $this->normalizeCategoryIds($categoryIds);
        $this->assertNotEmpty($categoryIds);

        $this->mutate($book, $categoryIds, function (Book $lockedBook) use ($categoryIds): void {
            $lockedBook->categories()->sync($categoryIds);
        });
    }

    /**
     * @param  array<int|string>  $categoryIds
     */
    public function detachCategories(Book $book, array $categoryIds): void
    {
        $categoryIds = $this->normalizeCategoryIds($categoryIds);

        if ($categoryIds === []) {
            return;
        }

        $this->mutate($book, [], function (Book $lockedBook) use ($categoryIds): void {
            $remaining = array_values(array_diff(
                $this->currentCategoryIds($lockedBook),
                $categoryIds,
            ));

            if ($remaining === []) {
                throw ValidationException::withMessages([
                    self::VALIDATION_KEY => self::MIN_CATEGORIES_MESSAGE,
                ]);
            }

            $lockedBook->categories()->detach($categoryIds);
        });
    }

    /**
     * @param  array<int|string>  $categoryIds
     */
    public function attachCategories(Book $book, array $categoryIds): void
    {
        $categoryIds = $this->normalizeCategoryIds($categoryIds);

        if ($categoryIds === []) {
            return;
        }

        $this->mutate($book, $categoryIds, function (Book $lockedBook) use ($categoryIds): void {
            $toAttach = array_values(array_diff(
                $categoryIds,
                $this->currentCategoryIds($lockedBook),
            ));

            if ($toAttach !== []) {
                $lockedBook->categories()->attach($toAttach);
            }
        });
    }

    /**
     * @param  iterable<Book>  $books
     */
    public function bulkAttachCategoryToBooks(iterable $books, int $categoryId): void
    {
        $categoryIds = $this->normalizeCategoryIds([$categoryId]);

        if ($categoryIds === []) {
            throw ValidationException::withMessages([
                self::VALIDATION_KEY => 'Danh mục không hợp lệ.',
            ]);
        }

        $bookIds = collect($books)
            ->map(static fn (Book $book): int => (int) $book->getKey())
            ->filter(static fn (int $bookId): bool => $bookId > 0)
            ->unique()
            ->sort()
            ->values();

        if ($bookIds->isEmpty()) {
            return;
        }

        try {
            DB::transaction(function () use ($bookIds, $categoryIds): void {
                $this->lockCategories($categoryIds);

                foreach ($bookIds as $bookId) {
                    $lockedBook = $this->lockBookById($bookId);

                    if (! $lockedBook->categories()->whereKey($categoryIds[0])->exists()) {
                        $lockedBook->categories()->attach($categoryIds[0]);
                    }
                }
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Bulk book category attach failed', [
                'category_id' => $categoryIds[0],
                'book_ids' => $bookIds->all(),
                'error' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            throw $exception;
        }
    }

    /**
     * @param  iterable<Book>  $books
     */
    public function bulkDetachCategoryFromBooks(iterable $books, int $categoryId): void
    {
        $categoryId = (int) $categoryId;

        if ($categoryId <= 0) {
            throw ValidationException::withMessages([
                self::VALIDATION_KEY => 'Danh mục không hợp lệ.',
            ]);
        }

        /** @var Collection<int, Book> $books */
        $books = collect($books)->values();

        try {
            DB::transaction(function () use ($books, $categoryId): void {
                foreach ($books as $book) {
                    $lockedBook = $this->lockBook($book);
                    $remaining = array_values(array_diff(
                        $this->currentCategoryIds($lockedBook),
                        [$categoryId],
                    ));

                    if ($remaining === []) {
                        throw ValidationException::withMessages([
                            self::VALIDATION_KEY => self::MIN_CATEGORIES_MESSAGE,
                        ]);
                    }
                }

                foreach ($books as $book) {
                    $lockedBook = $this->lockBook($book);
                    $lockedBook->categories()->detach([$categoryId]);
                }
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Bulk book category detach failed', [
                'category_id' => $categoryId,
                'book_ids' => $books->pluck('id')->all(),
                'error' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            throw $exception;
        }
    }

    /**
     * @param  list<int>  $categoryIdsToLock
     */
    private function mutate(Book $book, array $categoryIdsToLock, callable $callback): void
    {
        try {
            DB::transaction(function () use ($book, $categoryIdsToLock, $callback): void {
                $this->lockCategories($categoryIdsToLock);
                $callback($this->lockBook($book));
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Book category mutation failed', [
                'book_id' => $book->getKey(),
                'category_ids' => $categoryIdsToLock,
                'error' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            throw $exception;
        }
    }

    private function lockBook(Book $book): Book
    {
        return $this->lockBookById((int) $book->getKey());
    }

    private function lockBookById(int $bookId): Book
    {
        return Book::query()->whereKey($bookId)->lockForUpdate()->firstOrFail();
    }

    /**
     * @param  array<int|string>  $categoryIds
     * @return list<int>
     */
    private function normalizeCategoryIds(array $categoryIds): array
    {
        return array_values(array_unique(array_map(
            static fn ($id): int => (int) $id,
            array_filter($categoryIds, static fn ($id): bool => (int) $id > 0),
        )));
    }

    /**
     * @param  list<int>  $categoryIds
     */
    private function assertNotEmpty(array $categoryIds): void
    {
        if ($categoryIds === []) {
            throw ValidationException::withMessages([
                self::VALIDATION_KEY => self::MIN_CATEGORIES_MESSAGE,
            ]);
        }
    }

    /**
     * @param  list<int>  $categoryIds
     */
    private function lockCategories(array $categoryIds): void
    {
        if ($categoryIds === []) {
            return;
        }

        $existingIds = Category::query()
            ->whereKey($categoryIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id');

        if ($existingIds->count() !== count($categoryIds)) {
            throw ValidationException::withMessages([
                self::VALIDATION_KEY => 'Một hoặc nhiều danh mục không tồn tại.',
            ]);
        }
    }

    /**
     * @return list<int>
     */
    private function currentCategoryIds(Book $book): array
    {
        return $book->categories()
            ->pluck('categories.id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }
}
