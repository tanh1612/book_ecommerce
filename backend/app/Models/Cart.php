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
            if (empty($cart->account_id) && empty($cart->guest_token_hash)) {
                throw new \RuntimeException('Giỏ hàng bắt buộc phải có account_id hoặc guest_token_hash.');
            }
        });
    }

    protected $fillable = [
        'account_id',
        'guest_token_hash',
        'guest_token_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'guest_token_expires_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }
}
