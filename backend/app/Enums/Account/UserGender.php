<?php

namespace App\Enums\Account;

use Filament\Support\Contracts\HasLabel;

enum UserGender: string implements HasLabel
{
    case Male = 'male';
    case Female = 'female';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Male => 'Nam',
            self::Female => 'Nữ',
        };
    }
}
