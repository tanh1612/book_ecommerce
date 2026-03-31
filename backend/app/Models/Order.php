<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'account_id',
        'shipping_method_id',
        'total_amount',
        'shipping_fee',
        'final_amount',
        'shipping_name',
        'shipping_phone',
        'shipping_address',
        'payment_method',
        'payment_status',
        'note',
        'tracking_number',
        'current_status',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'final_amount' => 'decimal:2',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function timelines(): HasMany
    {
        return $this->hasMany(OrderTimeline::class)->orderBy('created_at');
    }
}
