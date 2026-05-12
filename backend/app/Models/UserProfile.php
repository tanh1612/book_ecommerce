<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    public const CREATED_AT = null;

    public $incrementing = false;

    protected $primaryKey = 'account_id';

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn () => trim("{$this->first_name} {$this->last_name}")
        );
    }

    protected $fillable = [
        'account_id',
        'first_name',
        'last_name',
        'phone',
        'gender',
        'birthday',
    ];

    protected function casts(): array
    {
        return [
            'birthday' => 'date',
            'updated_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
