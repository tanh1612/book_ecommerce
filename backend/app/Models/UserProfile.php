<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    public $timestamps = false;
    public $incrementing = false;

    protected $primaryKey = 'account_id';

    protected $fillable = [
        'account_id',
        'first_name',
        'last_name',
        'phone',
        'avatar_url',
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
