<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected static function booted()
    {
        static::saving(function ($item) {
            if ($item->quantity <= 0) {
                throw new \InvalidArgumentException('Số lượng của sản phẩm trong giỏ hàng phải lớn hơn 0.');
            }
        });
    }

    protected $fillable = [
        'cart_id',
        'book_id',
        'quantity',
        'selected',
    ];

    protected function casts(): array
    {
        return [
            'selected' => 'boolean',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
