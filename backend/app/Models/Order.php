<?php

namespace App\Models;

use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'account_id',
        'checkout_idempotency_key',
        'shipping_method_id',
        'total_amount',
        'shipping_fee',
        'final_amount',
        'shipping_name',
        'shipping_phone',
        'shipping_address',
        'payment_method',
        'payment_status',
        'payment_expires_at',
        'note',
        'current_status',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'shipping_fee' => 'decimal:2',
            'final_amount' => 'decimal:2',
            'current_status' => \App\Enums\Order\OrderStatus::class,
            'payment_method' => \App\Enums\Order\PaymentMethod::class,
            'payment_status' => \App\Enums\Order\PaymentStatus::class,
            'payment_expires_at' => 'datetime',
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

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function isAdminDeletable(): bool
    {
        return $this->current_status === OrderStatus::CONFIRMED
            && $this->payment_status === PaymentStatus::PENDING;
    }
}
