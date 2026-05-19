<?php

namespace App\Models;

use App\Enums\Payment\PaymentGateway;
use App\Enums\Payment\PaymentTransactionStatus;
use App\Enums\Payment\PaymentTransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'order_id',
        'gateway',
        'gateway_txn_id',
        'type',
        'amount',
        'status',
        'payload',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'gateway' => PaymentGateway::class,
            'type' => PaymentTransactionType::class,
            'status' => PaymentTransactionStatus::class,
            'amount' => 'decimal:2',
            'payload' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
