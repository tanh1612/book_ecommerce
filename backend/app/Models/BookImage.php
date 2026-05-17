<?php

namespace App\Models;

use App\Services\Media\BookImageStorageService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookImage extends Model
{
    /** @use HasFactory<\Database\Factories\BookImageFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected static function booted()
    {
        static::creating(function (BookImage $image): void {
            if ($image->sort_order === null) {
                $max = static::query()->where('book_id', $image->book_id)->max('sort_order');
                $image->sort_order = ($max ?? 0) + 1;
            }
        });

        static::saving(function (BookImage $image): void {
            if ($image->isDirty('public_id') && $image->public_id !== null && $image->public_id !== '') {
                $image->image_url = app(BookImageStorageService::class)->deliveryUrlFromPublicId((string) $image->public_id);
            }
        });
    }

    protected $fillable = [
        'public_id',
        'sort_order',
    ];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
