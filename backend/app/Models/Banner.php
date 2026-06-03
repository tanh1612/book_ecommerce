<?php

namespace App\Models;

use App\Services\Media\BannerImageStorageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class Banner extends Model
{
    /** @use HasFactory<\Database\Factories\BannerFactory> */
    use HasFactory;
    protected static function booted(): void
    {
        static::saving(function (Banner $banner): void {
            if ($banner->isDirty('public_id') && $banner->public_id !== null && $banner->public_id !== '') {
                $banner->image_url = app(BannerImageStorageService::class)
                    ->deliveryUrlFromPublicId((string) $banner->public_id);
            }

            if ($banner->public_id !== null && $banner->public_id !== '') {
                $duplicateExists = static::query()
                    ->where('public_id', $banner->public_id)
                    ->when($banner->exists, fn (Builder $query) => $query->whereKeyNot($banner->id))
                    ->exists();

                if ($duplicateExists) {
                    throw ValidationException::withMessages([
                        'public_id' => ['Ảnh banner này đã được gán cho banner khác.'],
                    ]);
                }
            }
        });
    }

    protected $fillable = [
        'title',
        'public_id',
        'image_url',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Storefront only shows manually enabled banners in this phase.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
