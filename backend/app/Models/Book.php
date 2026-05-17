<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class Book extends Model
{
    /** @use HasFactory<\Database\Factories\BookFactory> */
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'publisher_id',
        'name',
        'slug',
        'sku',
        'thumbnail',
        'original_price',
        'selling_price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'original_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'average_rating' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Publisher::class);
    }

    public function detail(): HasOne
    {
        return $this->hasOne(BookDetail::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(BookImage::class)->orderBy('sort_order');
    }

    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(Author::class, 'book_authors');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'book_categories');
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function promotionItems(): HasMany
    {
        return $this->hasMany(PromotionItem::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Storefront chỉ hiển thị sách đang bật; dùng chung cho API catalog và metadata bộ lọc.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    protected function thumbnail(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): ?string {
                if ($value !== null && $value !== '') {
                    if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
                        return $value;
                    }

                    return Storage::disk('cloudinary')->url($value);
                }

                if (! $this->relationLoaded('images')) {
                    return BookImage::query()
                        ->where('book_id', $this->id)
                        ->orderBy('sort_order')
                        ->orderBy('id')
                        ->value('image_url');
                }

                return $this->images->sortBy('sort_order')->first()?->image_url;
            },
        );
    }
}
