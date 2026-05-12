<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Publisher extends Model
{
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
