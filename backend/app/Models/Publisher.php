<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Publisher extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'name',
        'email',
    ];

    public function bookDetails(): HasMany
    {
        return $this->hasMany(BookDetail::class);
    }

    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }
}
