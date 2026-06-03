<?php

namespace App\Services\Media;

use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class BannerImageStorageService
{
    public const CLOUDINARY_APP_ROOT = 'book_ecommerce';

    public const HOME_BANNERS_SEGMENT = 'banners/home';

    public function deliveryUrlFromPublicId(string $publicId): string
    {
        $publicId = trim($publicId);

        return Storage::disk('cloudinary')->url($publicId);
    }

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
            Log::error('Cloudinary delete banner asset failed', [
                'public_id' => $publicId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function homeBannersFolder(): string
    {
        return self::CLOUDINARY_APP_ROOT.'/'.self::HOME_BANNERS_SEGMENT;
    }

    /**
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

    public function newBannerImageBasename(): string
    {
        $suffix = strtolower(str_replace('-', '', (string) Str::ulid()));

        return 'home-banner-'.$suffix;
    }

    public function newBannerImagePublicId(): string
    {
        return $this->homeBannersFolder().'/'.$this->newBannerImageBasename();
    }
}
