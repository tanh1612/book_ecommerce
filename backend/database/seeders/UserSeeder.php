<?php

namespace Database\Seeders;

use App\Enums\Account\AccountRole;
use App\Models\Account;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        Account::firstOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'password' => Hash::make('12345678'),
                'role' => AccountRole::Admin,
                'is_active' => true,
            ]
        );

    }
}
