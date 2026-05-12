<?php

namespace App\Models;

use App\Enums\Account\AccountRole;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Account extends Authenticatable implements FilamentUser, HasName
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $table = 'accounts';

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->role === AccountRole::Admin;
    }

    public function getFilamentName(): string
    {
        if ($this->profile && $this->profile->full_name !== '') {
            return $this->profile->full_name;
        }

        return $this->email ?? 'Admin';
    }

    protected $fillable = [
        'email',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'role' => AccountRole::class,
        ];
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class, 'account_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class, 'account_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'account_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'account_id');
    }

    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class, 'account_id');
    }
}
