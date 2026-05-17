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

    public static function uniqueBookIdForInventory(?Model $record): \Illuminate\Validation\Rules\Unique
    {
        $ignoreId = $record instanceof Inventory ? $record->getKey() : null;

        return Rule::unique('inventories', 'book_id')->ignore($ignoreId);
    }

    /**
     * RelationManager on Book: book_id is implicit; block a second inventory row for the same book.
     *
     * @return \Closure(string, mixed, \Closure): void
     */
    public static function assertOwnerBookHasAtMostOneInventoryRelation(RelationManager $livewire, ?Model $record): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($livewire, $record): void {
            $bookId = (int) $livewire->ownerRecord->getKey();
            $query = Inventory::query()->where('book_id', $bookId);
            if ($record instanceof Inventory) {
                $query->whereKeyNot($record->getKey());
            }
            if ($query->exists()) {
                $fail('Cuốn sách này đã có dòng tồn kho; mỗi sách chỉ được một dòng.');
            }
        };
    }
}
