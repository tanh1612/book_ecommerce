<?php

namespace App\Models;

use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentMethod;
use App\Enums\Order\PaymentStatus;
use App\Enums\Payment\PaymentTransactionStatus;
use App\Enums\Payment\PaymentTransactionType;
use Carbon\CarbonInterface;
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
        'refund_deadline_at',
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
            'refund_deadline_at' => 'datetime',
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

    public function pendingRefundTransaction(): ?PaymentTransaction
    {
        return $this->paymentTransactions()
            ->where('type', PaymentTransactionType::REFUND)
            ->where('status', PaymentTransactionStatus::PENDING)
            ->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function submittedRefundBankInfo(): ?array
    {
        $txn = $this->pendingRefundTransaction()
            ?? $this->paymentTransactions()
                ->where('type', PaymentTransactionType::REFUND)
                ->latest('id')
                ->first();

        if ($txn === null) {
            return null;
        }

        $bankInfo = $txn->payload['bank_info'] ?? null;

        if (! is_array($bankInfo)) {
            return null;
        }

        return $bankInfo;
    }

    public function canPay(): bool
    {
        if ($this->payment_method !== PaymentMethod::VNPAY) {
            return false;
        }

        if ($this->payment_status !== PaymentStatus::PENDING) {
            return false;
        }

        if ($this->current_status !== OrderStatus::PENDING) {
            return false;
        }

        if ($this->payment_expires_at instanceof CarbonInterface && $this->payment_expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function canSubmitRefundBankInfo(): bool
    {
        if ($this->payment_status !== PaymentStatus::REFUNDING) {
            return false;
        }

        if ($this->refund_deadline_at !== null && $this->refund_deadline_at->isPast()) {
            return false;
        }

        if ($this->pendingRefundTransaction() === null) {
            return false;
        }

        return $this->submittedRefundBankInfo() === null;
    }
}
