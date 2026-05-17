<?php

namespace App\Observers;

use App\Models\Book;
use App\Models\BookImage;
use App\Services\Media\BookImageStorageService;

class BookImageObserver
{
    public function __construct(
        private BookImageStorageService $bookImageStorage,
    ) {}

    /**
     * Handle the BookImage "creating" event.
     */
    public function creating(BookImage $bookImage): void
    {
        if (! $bookImage->public_id && $bookImage->image_url) {
            $bookImage->public_id = $this->bookImageStorage->extractPublicIdFromUrl($bookImage->image_url);
        }
    }

    /**
     * Handle the BookImage "updating" event.
     */
    public function updating(BookImage $bookImage): void
    {
        if ($bookImage->isDirty('image_url') && $bookImage->image_url) {
            $bookImage->public_id = $this->bookImageStorage->extractPublicIdFromUrl($bookImage->image_url);
        }
    }

    /**
     * Handle the BookImage "saved" event.
     */
    public function saved(BookImage $bookImage): void
    {
        $this->updateBookThumbnail($bookImage->book);
    }

    /**
     * Handle the BookImage "deleted" event.
     */
    public function deleted(BookImage $bookImage): void
    {
        $this->bookImageStorage->deleteByPublicId($bookImage->getOriginal('public_id'));

        $bookId = $bookImage->getOriginal('book_id');
        if ($bookId === null) {
            return;
        }

        $book = Book::query()->find($bookId);
        if ($book === null) {
            return;
        }

        $this->bookImageStorage->normalizeSortOrdersForBook($book);
        $this->updateBookThumbnail($book);
    }

    /**
     * Common logic to update the Book thumbnail based on the primary image.
     */
    protected function updateBookThumbnail(?Book $book): void
    {
        if ($book === null) {
            return;
        }

        $primaryImage = $book->images()->orderBy('sort_order')->orderBy('id')->first();

        if ($primaryImage !== null) {
            $delivery = $this->bookImageStorage->deliveryUrlFromPublicId((string) $primaryImage->public_id);
            $thumbnailUrl = $this->bookImageStorage->thumbnailDeliveryUrlFromDeliveryUrl($delivery);
            $book->forceFill(['thumbnail' => $thumbnailUrl])->saveQuietly();
        } else {
            $book->forceFill(['thumbnail' => null])->saveQuietly();
        }
    }
}
