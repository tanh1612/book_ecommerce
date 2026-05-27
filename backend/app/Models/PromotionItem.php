<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromotionItem extends Model
{
    protected $fillable = [
        'promotion_id',
        'book_id',
        'discount_value',
        'stock_limit',
        'sold_quantity',
        'max_quantity_per_user',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'integer',
        ];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PromotionAllocation::class);
    }
}
