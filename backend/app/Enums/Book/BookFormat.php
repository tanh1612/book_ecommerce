<?php

namespace App\Enums\Book;

use Filament\Support\Contracts\HasLabel;

enum BookFormat: string implements HasLabel
{
    case PAPERBACK = 'paperback';
    case HARDCOVER = 'hardcover';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PAPERBACK => 'Bìa mềm',
            self::HARDCOVER => 'Bìa cứng',
        };
    }
}
