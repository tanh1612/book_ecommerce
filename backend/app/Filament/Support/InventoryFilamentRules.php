<?php

namespace App\Filament\Support;

use Filament\Schemas\Components\Utilities\Get;

/**
 * Shared validation for Filament inventory forms (Resource + RelationManagers).
 */
final class InventoryFilamentRules
{
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
