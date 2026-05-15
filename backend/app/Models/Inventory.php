<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventory extends Model
{
    /** @use HasFactory<\Database\Factories\InventoryFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'book_id',
        'warehouse_id',
        'quantity',
        'sold_quantity',
        'reserved_quantity',
        'location_code',
        'last_restocked_at',
    ];

    protected function casts(): array
    {
        return [
            'last_restocked_at' => 'datetime',
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Số lượng còn có thể bán = tồn kho − đã giữ chỗ (không âm).
     */
    protected function availableStock(): Attribute
    {
        return Attribute::make(
            get: fn (): int => max(0, (int) $this->quantity - (int) $this->reserved_quantity),
        );
    }
}
