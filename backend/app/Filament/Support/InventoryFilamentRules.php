<?php

namespace App\Filament\Support;

use App\Models\Inventory;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Shared validation for Filament inventory forms (Resource + RelationManagers).
 */
final class InventoryFilamentRules
{
    public static function availableStockBadgeColor(Inventory $inventory): string
    {
        $stock = $inventory->available_stock;
        $threshold = (int) config('inventory.low_stock_threshold', 5);

        return match (true) {
            $stock <= 0 => 'danger',
            $stock <= $threshold => 'warning',
            default => 'success',
        };
    }

    public static function lowStockFilterLabel(): string
    {
        $threshold = (int) config('inventory.low_stock_threshold', 5);

        return "Sắp hết (1–{$threshold})";
    }

    /**
     * @return \Closure(Get): \Closure(string, mixed, \Closure): void
     */
    public static function reservedQuantityLteOnHand(): \Closure
    {
        return fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
            $onHand = (int) $get('quantity');
            if ((int) $value > $onHand) {
                $fail('reserved_quantity must not exceed quantity.');
            }
        };
    }

    /**
     * When reserved_quantity is read-only on edit, validate quantity against it instead.
     *
     * @return \Closure(Get): \Closure(string, mixed, \Closure): void
     */
    public static function quantityGteReserved(): \Closure
    {
        return fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
            $reserved = (int) $get('reserved_quantity');
            if ((int) $value < $reserved) {
                $fail('Số lượng tồn không được nhỏ hơn số đang giữ.');
            }
        };
    }

}
