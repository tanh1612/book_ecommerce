<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Account extends Authenticatable implements FilamentUser, HasName
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $table = 'accounts';

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->role === 'admin';
    }

    public function getFilamentName(): string
    {
        return $this->profile ? trim($this->profile->first_name . ' ' . $this->profile->last_name) : $this->email;
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
        ];
    }

    public function profile()
    {
        return $this->hasOne(UserProfile::class, 'account_id');
    }

    public function addresses()
    {
        return $this->hasMany(Address::class, 'account_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'account_id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'account_id');
    }

    public function cart()
    {
        return $this->hasOne(Cart::class, 'account_id');
    }
}
