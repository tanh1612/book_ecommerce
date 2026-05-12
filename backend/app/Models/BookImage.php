<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class BookImage extends Model
{
    public const UPDATED_AT = null;

    protected static function booted()
    {
        static::creating(function ($image) {
            if (is_null($image->sort_order)) {
                $image->sort_order = static::where('book_id', $image->book_id)->max('sort_order') + 1;
            }
        });

        static::saving(function ($image) {
            if ($image->isDirty('public_id') && ! empty($image->public_id)) {
                $image->image_url = Storage::disk('cloudinary')->url($image->public_id);
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
