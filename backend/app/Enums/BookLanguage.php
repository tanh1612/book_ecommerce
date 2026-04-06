<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum BookLanguage: string implements HasLabel
{
    case VI = 'vi';
    case EN = 'en';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::VI => 'Tiếng Việt',
            self::EN => 'Tiếng Anh',
        };
    }
}
