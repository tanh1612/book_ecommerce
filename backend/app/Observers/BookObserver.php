<?php

namespace App\Observers;

use App\Models\Book;
use App\Models\CartItem;
use App\Services\Catalog\CatalogCacheService;
use App\Services\Media\BookImageStorageService;
use Illuminate\Support\Facades\Log;
use Throwable;

class BookObserver
{
    public function __construct(
        private BookImageStorageService $bookImageStorage,
        private CatalogCacheService $catalogCache,
    ) {}

    public function saved(Book $book): void
    {
        $this->catalogCache->forgetBookBySlug((string) $book->slug);

        if ($book->wasChanged('slug')) {
            $originalSlug = $book->getOriginal('slug');
            if (is_string($originalSlug) && $originalSlug !== '') {
                $this->catalogCache->forgetBookBySlug($originalSlug);
            }
        }

        if ($book->wasRecentlyCreated || $book->wasChanged(['is_active', 'supplier_id', 'publisher_id'])) {
            $this->catalogCache->forgetFiltersMetadata();
        }

        if ($book->wasChanged('is_active') && ! $book->is_active) {
            $this->removeBookFromAllCarts($book);
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
        $this->catalogCache->forgetBookBySlug((string) $book->slug);
        $this->catalogCache->forgetFiltersMetadata();

        $images = $book->images()->get(['public_id']);

        foreach ($images as $image) {
            $this->bookImageStorage->deleteByPublicId($image->public_id);
        }

        $this->bookImageStorage->deleteEmptyBookFolderForSlug((string) $book->slug);
    }
}
