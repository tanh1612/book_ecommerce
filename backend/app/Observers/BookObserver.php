<?php

namespace App\Observers;

use App\Models\Book;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Support\Facades\Log;

class BookObserver
{
    /**
     * Handle the Book "deleting" event.
     */
    public function deleting(Book $book): void
    {
        // 1. Purge all gallery images from Cloudinary
        foreach ($book->images as $image) {
            if ($image->public_id) {
                try {
                    Cloudinary::destroy($image->public_id);
                } catch (\Exception $e) {
                    Log::error("Failed to purge image from Cloudinary for book '{$book->id}': " . $e->getMessage());
                }
            }
        }
    }
}
