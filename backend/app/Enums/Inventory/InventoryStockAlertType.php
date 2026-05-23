<?php

namespace App\Enums\Inventory;

use Filament\Support\Contracts\HasLabel;

enum InventoryStockAlertType: string implements HasLabel
{
    case LowStock = 'low_stock';
    case OutOfStock = 'out_of_stock';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::LowStock => 'Sắp hết hàng',
            self::OutOfStock => 'Hết hàng',
        };
    }
}
