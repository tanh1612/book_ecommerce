<?php

namespace App\Models;

use App\Enums\Promotion\PromotionAllocationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionAllocation extends Model
{
    protected $fillable = [
        'promotion_item_id',
        'account_id',
        'order_id',
        'order_item_id',
        'quantity',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => PromotionAllocationStatus::class,
        ];
    }

    public function promotionItem(): BelongsTo
    {
        return $this->belongsTo(PromotionItem::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
