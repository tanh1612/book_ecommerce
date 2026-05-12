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
        // 1. Purge all images and the book folder from Cloudinary
        if ($slug = $book->slug) {
            $folderPath = "books/{$slug}";
            try {
                // Delete all assets in the folder
                \CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary::adminApi()->deleteAssetsByPrefix($folderPath . '/');
                
                // Delete the folder itself
                \CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary::adminApi()->deleteFolder($folderPath);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Failed to purge Cloudinary folder '{$folderPath}': " . $e->getMessage());
            }
        }
    }
}
