<?php

namespace App\Models;

use App\Enums\Promotion\PromotionStatus;
use App\Enums\Promotion\PromotionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promotion extends Model
{
    protected $fillable = [
        'name',
        'type',
        'start_at',
        'end_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'type' => PromotionType::class,
            'status' => PromotionStatus::class,
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PromotionItem::class);
    }
}
