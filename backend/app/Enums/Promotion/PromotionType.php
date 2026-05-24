<?php

namespace App\Enums\Promotion;

use Filament\Support\Contracts\HasLabel;

enum PromotionType: string implements HasLabel
{
    case FLASH_SALE = 'flash_sale';
    case REGULAR_SALE = 'discount';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::FLASH_SALE => 'Flash sale',
            self::REGULAR_SALE => 'Sale thường',
        };
    }
}
