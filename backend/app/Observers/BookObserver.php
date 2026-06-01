<?php

namespace App\Observers;

use App\Models\Book;
use App\Models\CartItem;
use App\Services\Catalog\CatalogCacheService;
use App\Services\Media\BookImageStorageService;
use App\Services\Ai\BookRagSyncDispatcher;
use Illuminate\Support\Facades\Log;
use Throwable;

class BookObserver
{
    public function __construct(
        private BookImageStorageService $bookImageStorage,
        private CatalogCacheService $catalogCache,
        private BookRagSyncDispatcher $bookRagSync,
    ) {}

    public function saved(Book $book): void
    {
        $slugs = [(string) $book->slug];
        if ($book->wasChanged('slug')) {
            $originalSlug = $book->getOriginal('slug');
            if (is_string($originalSlug) && $originalSlug !== '') {
                $slugs[] = $originalSlug;
            }
        }
        $this->catalogCache->forgetBookSlugsAfterCommit($slugs);

        if ($book->wasRecentlyCreated || $book->wasChanged(['is_active', 'supplier_id', 'publisher_id'])) {
            $this->catalogCache->forgetFiltersMetadataAfterCommit();
        }

        if ($book->wasChanged('is_active') && ! $book->is_active) {
            $this->removeBookFromAllCarts($book);
        }

        if ($book->wasChanged(['name', 'is_active', 'publisher_id'])) {
            $this->bookRagSync->dispatch((int) $book->id);
        }
    }

    private function removeBookFromAllCarts(Book $book): void
    {
        try {
            CartItem::query()->where('book_id', $book->id)->delete();
        } catch (Throwable $e) {
            Log::error('Remove inactive book from carts failed', [
                'book_id' => $book->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the Book "deleting" event.
     */
    public function deleting(Book $book): void
    {
        $this->catalogCache->forgetBookBySlugAfterCommit((string) $book->slug);
        $this->catalogCache->forgetFiltersMetadataAfterCommit();

        $images = $book->images()->get(['public_id']);

        foreach ($images as $image) {
            $this->bookImageStorage->deleteByPublicId($image->public_id);
        }

        $this->bookImageStorage->deleteEmptyBookFolderForSlug((string) $book->slug);
    }
}
