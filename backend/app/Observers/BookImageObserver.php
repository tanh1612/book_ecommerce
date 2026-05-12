<?php

namespace App\Observers;

use App\Models\Book;
use App\Models\BookImage;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Support\Facades\Log;

class BookImageObserver
{
    /**
     * Handle the BookImage "creating" event.
     */
    public function creating(BookImage $bookImage): void
    {
        // 1. Extract public_id from URL if not provided
        if (!$bookImage->public_id && $bookImage->image_url) {
            $bookImage->public_id = $this->extractPublicId($bookImage->image_url);
        }

        // 2. Handle sort_order if null
        if ($bookImage->sort_order === null) {
            $max = BookImage::where('book_id', $bookImage->book_id)->max('sort_order');
            $bookImage->sort_order = ($max ?? 0) + 1;
        }
    }

    /**
     * Handle the BookImage "updating" event.
     */
    public function updating(BookImage $bookImage): void
    {
        // 1. If image_url changed, update public_id
        if ($bookImage->isDirty('image_url')) {
            $bookImage->public_id = $this->extractPublicId($bookImage->image_url);
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
        // 1. Delete physical file from Cloudinary
        if ($bookImage->public_id) {
            try {
                // Cloudinary Admin/Upload API requires public_id without extension
                $publicIdWithoutExtension = preg_replace('/\.[^.]+$/', '', $bookImage->public_id);
                
                \CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary::uploadApi()->destroy($publicIdWithoutExtension);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Failed to delete image from Cloudinary: " . $e->getMessage());
            }
        }

        // 2. Update Book thumbnail
        $this->updateBookThumbnail($bookImage->book);
    }

    /**
     * Common logic to update the Book thumbnail based on the primary image.
     */
    protected function updateBookThumbnail(?Book $book): void
    {
        if (!$book) {
            return;
        }

        // Get the image with the lowest sort_order (the primary image)
        $primaryImage = $book->images()->orderBy('sort_order')->orderBy('id')->first();

        if ($primaryImage) {
            // Apply Cloudinary transformation for the thumbnail
            // Format: c_fill,g_auto,w_300,h_400 (as per plan's suggestion or professional default)
            $thumbnailUrl = $this->generateThumbnailUrl($primaryImage->image_url);
            
            $book->update(['thumbnail' => $thumbnailUrl]);
        } else {
            // No images left, set thumbnail to null
            $book->update(['thumbnail' => null]);
        }
    }

    /**
     * Extracts public_id from Cloudinary URL.
     */
    protected function extractPublicId(string $url): string
    {
        // Format: https://res.cloudinary.com/[cloud]/image/upload/v[v]/[folder]/[public_id].[ext]
        // Example: https://res.cloudinary.com/demo/image/upload/v123/books/sample.jpg -> books/sample
        
        $path = parse_url($url, PHP_URL_PATH);
        
        // Remove /image/upload/ and version string
        $path = preg_replace('/^\/[^\/]+\/[^\/]+\/upload\/(v\d+\/)?/', '', $path);
        
        // Remove extension
        $path = preg_replace('/\.[^.]+$/', '', $path);

        return $path;
    }

    /**
     * Injects Cloudinary transformation parameters into the URL.
     */
    protected function generateThumbnailUrl(string $url): string
    {
        // Inject /c_fill,g_auto,w_300,h_400 after /upload/
        $search = '/upload/';
        $transformation = '/upload/c_fill,g_auto,w_300,h_400/';
        
        if (str_contains($url, $search) && !str_contains($url, $transformation)) {
            // Ensure we don't inject multiple times
            return str_replace($search, $transformation, $url);
        }

        return $url;
    }
}
