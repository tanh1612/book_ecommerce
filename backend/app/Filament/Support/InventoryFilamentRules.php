<?php

namespace App\Filament\Support;

use App\Models\Inventory;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

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

    public static function uniqueBookWarehouseForResource(Get $get, ?Model $record): \Illuminate\Validation\Rules\Unique
    {
        $ignoreId = $record instanceof Inventory ? $record->getKey() : null;

        return Rule::unique('inventories', 'warehouse_id')
            ->where('book_id', $get('book_id'))
            ->ignore($ignoreId);
    }

    public static function uniqueWarehouseBookForResource(Get $get, ?Model $record): \Illuminate\Validation\Rules\Unique
    {
        $ignoreId = $record instanceof Inventory ? $record->getKey() : null;

        return Rule::unique('inventories', 'book_id')
            ->where('warehouse_id', $get('warehouse_id'))
            ->ignore($ignoreId);
    }

    public static function uniqueWarehouseForBookRelation(Get $get, RelationManager $livewire, ?Model $record): \Illuminate\Validation\Rules\Unique
    {
        $ignoreId = $record instanceof Inventory ? $record->getKey() : null;

        return Rule::unique('inventories', 'warehouse_id')
            ->where('book_id', $livewire->ownerRecord->getKey())
            ->ignore($ignoreId);
    }

    public static function uniqueBookForWarehouseRelation(Get $get, RelationManager $livewire, ?Model $record): \Illuminate\Validation\Rules\Unique
    {
        $ignoreId = $record instanceof Inventory ? $record->getKey() : null;

        return Rule::unique('inventories', 'book_id')
            ->where('warehouse_id', $livewire->ownerRecord->getKey())
            ->ignore($ignoreId);
    }
}
