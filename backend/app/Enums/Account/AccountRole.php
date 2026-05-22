<?php

namespace App\Enums\Account;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AccountRole: string implements HasColor, HasLabel
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

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Admin => 'danger',
            self::Customer => 'success',
        };
    }
}
