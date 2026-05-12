<?php

namespace App\Enums\Account;

use Filament\Support\Contracts\HasLabel;

enum AccountRole: string implements HasLabel
{
    case Admin = 'admin';
    case Customer = 'customer';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Admin => 'Quản trị viên',
            self::Customer => 'Khách hàng',
        };
    }
}
