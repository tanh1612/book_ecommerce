<?php

namespace App\Models;

use App\Enums\Review\ReviewStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = [
        'account_id',
        'book_id',
        'order_item_id',
        'rating',
        'comment',
        'status',
        'admin_reply',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'decimal:1',
            'status' => ReviewStatus::class,
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
