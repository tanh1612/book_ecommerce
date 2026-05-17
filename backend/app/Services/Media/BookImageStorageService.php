<?php

namespace App\Services\Media;

use App\Models\Book;
use App\Models\BookImage;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class BookImageStorageService
{
    /**
     * Root folder for all Bookify assets on Cloudinary (Media Library).
     */
    public const CLOUDINARY_APP_ROOT = 'book_ecommerce';

    /**
     * Public delivery URL for a stored Cloudinary public_id (path, no leading slash).
     */
    public function deliveryUrlFromPublicId(string $publicId): string
    {
        $publicId = trim($publicId);

        return Storage::disk('cloudinary')->url($publicId);
    }

    /**
     * Same image with a lightweight default transform for admin thumbnails / book.thumbnail snapshot.
     */
    public function thumbnailDeliveryUrlFromDeliveryUrl(string $deliveryUrl): string
    {
        $search = '/upload/';
        $transformation = '/upload/c_fill,g_auto,w_300,h_400,q_auto,f_auto/';

        if (str_contains($deliveryUrl, $search) && ! str_contains($deliveryUrl, $transformation)) {
            return str_replace($search, $transformation, $deliveryUrl);
        }

        return $deliveryUrl;
    }

    /**
     * Cloudinary destroy() expects public_id without file extension.
     */
    public function normalizeDestroyPublicId(string $publicId): string
    {
        $publicId = trim($publicId);

        return (string) preg_replace('/\.[^.]+$/', '', $publicId);
    }

    public function deleteByPublicId(?string $publicId): void
    {
        if ($publicId === null || $publicId === '') {
            return;
        }

        try {
            $id = $this->normalizeDestroyPublicId($publicId);
            Cloudinary::uploadApi()->destroy($id, ['resource_type' => 'image']);
        } catch (Throwable $e) {
            Log::error('Cloudinary delete asset failed', [
                'public_id' => $publicId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Re-number sort_order to 1..n after delete/reorder drift (no model events).
     */
    public function normalizeSortOrdersForBook(Book $book): void
    {
        $items = BookImage::query()
            ->where('book_id', $book->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'sort_order']);

        foreach ($items as $index => $item) {
            $expected = $index + 1;
            if ((int) $item->sort_order !== $expected) {
                BookImage::query()->whereKey($item->id)->update(['sort_order' => $expected]);
            }
        }
    }

    /**
     * Extract public_id segment from a typical Cloudinary HTTPS URL (for legacy rows).
     */
    public function extractPublicIdFromUrl(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $path = (string) preg_replace('#^/[^/]+/[^/]+/upload/(v\d+/)?#', '', $path);

        return (string) preg_replace('/\.[^.]+$/', '', $path);
    }

    /**
     * Slug segment safe for Cloudinary folder / public_id path.
     */
    public function normalizeBookSlug(string $slug): string
    {
        $slug = Str::slug($slug);

        return $slug !== '' ? $slug : 'book';
    }

    /**
     * Folder path for one book's images: book_ecommerce/books/{slug}
     */
    public function bookImagesFolderForSlug(string $slug): string
    {
        return self::CLOUDINARY_APP_ROOT.'/books/'.$this->normalizeBookSlug($slug);
    }

    /**
     * Options for Upload API so assets appear under the correct folder in Media Library.
     *
     * @return array<string, mixed>
     */
    public function cloudinaryUploadOptionsForImageAtPath(string $logicalPublicId): array
    {
        $logicalPublicId = ltrim(str_replace('\\', '/', trim($logicalPublicId)), '/');
        $folder = dirname($logicalPublicId);
        $publicId = basename($logicalPublicId);

        if ($folder === '.' || $folder === '') {
            return [
                'public_id' => $publicId,
                'resource_type' => 'image',
            ];
        }

        return [
            'folder' => $folder,
            'public_id' => $publicId,
            'resource_type' => 'image',
        ];
    }

    /**
     * File basename only (no folders) for Filament storeAs(directory, name).
     */
    public function newBookImageBasename(string $slug): string
    {
        $segment = $this->normalizeBookSlug($slug);
        $suffix = strtolower(str_replace('-', '', (string) Str::ulid()));

        return $segment.'-'.$suffix;
    }

    /**
     * Full public_id for a new gallery image (path without extension; Cloudinary may add format in delivery URL).
     */
    public function newBookImagePublicId(string $slug): string
    {
        return $this->bookImagesFolderForSlug($slug).'/'.$this->newBookImageBasename($slug);
    }
}
