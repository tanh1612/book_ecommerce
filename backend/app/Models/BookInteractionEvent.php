<?php

namespace App\Models;

use App\Enums\Recommendation\BookInteractionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookInteractionEvent extends Model
{
    /** @use HasFactory<\Database\Factories\BookInteractionEventFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'account_id',
        'book_id',
        'event_type',
        'source',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => BookInteractionType::class,
            'created_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class, 'book_id');
    }
}
