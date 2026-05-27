<?php

namespace App\Enums\Promotion;

use Filament\Support\Contracts\HasLabel;

enum PromotionType: string implements HasLabel
{
    case FLASH_SALE = 'flash_sale';

    public function getLabel(): ?string
    {
        return 'Flash sale';
    }
}
