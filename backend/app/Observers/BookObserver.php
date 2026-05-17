<?php

namespace App\Observers;

use App\Models\Book;
use App\Services\Media\BookImageStorageService;

class BookObserver
{
    public function __construct(
        private BookImageStorageService $bookImageStorage,
    ) {}

    /**
     * Handle the Book "deleting" event.
     */
    public function deleting(Book $book): void
    {
        $images = $book->images()->get(['public_id']);

        foreach ($images as $image) {
            $this->bookImageStorage->deleteByPublicId($image->public_id);
        }
    }
}
