<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Publisher extends Model
{
    /** @use HasFactory<\Database\Factories\PublisherFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'name',
        'email',
    ];

    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }
}
