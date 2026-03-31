<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookDetail extends Model
{
    public $timestamps = false;
    public $incrementing = false;

    protected $primaryKey = 'book_id';

    protected $fillable = [
        'book_id',
        'description',
        'language',
        'translator',
        'publication_year',
        'weight',
        'dimensions',
        'num_pages',
        'format',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
