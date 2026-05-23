<?php

namespace App\Services\Inventory;

use App\Models\Inventory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LowStockAlertService
{
    /**
     * @return Collection<int, LowStockAlertItem>
     */
    public function getLowStockItems(): Collection
    {
        return $this->lowStockQuery()
            ->with(['book', 'warehouse'])
            ->orderByRaw('(quantity - reserved_quantity) ASC')
            ->orderBy('book_id')
            ->get()
            ->map(fn (Inventory $inventory): LowStockAlertItem => LowStockAlertItem::fromInventory($inventory));
    }

    public function countLowStockBooks(): int
    {
        return $this->lowStockQuery()->count();
    }

    /**
     * @return Collection<int, int>
     */
    public function lowStockInventoryIds(): Collection
    {
        return $this->lowStockQuery()
            ->orderBy('id')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id);
    }

    public function lowStockSetHash(Collection $inventoryIds): string
    {
        return hash('sha256', $inventoryIds->sort()->values()->implode(','));
    }

    public static function applyLowStockScope(Builder $query): Builder
    {
        $threshold = (int) config('inventory.low_stock_threshold', 5);

        return $query
            ->whereHas('book', fn (Builder $bookQuery): Builder => $bookQuery->where('is_active', true))
            ->whereRaw('(quantity - reserved_quantity) > 0')
            ->whereRaw('(quantity - reserved_quantity) <= ?', [$threshold]);
    }

    private function lowStockQuery(): Builder
    {
        return static::applyLowStockScope(Inventory::query());
    }
}
