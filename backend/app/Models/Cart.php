<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    protected static function booted()
    {
        static::saving(function ($cart) {
            if (empty($cart->account_id) && empty($cart->session_id)) {
                throw new \RuntimeException('Giỏ hàng bắt buộc phải có account_id hoặc session_id.');
            }
        });
    }

    protected $fillable = [
        'account_id',
        'session_id',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }
}
