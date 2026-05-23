<?php

namespace App\Services\Inventory;

use App\Models\Inventory;

final readonly class LowStockAlertItem
{
    public function __construct(
        public int $inventoryId,
        public int $bookId,
        public string $bookName,
        public string $sku,
        public int $quantity,
        public int $reservedQuantity,
        public int $availableStock,
        public string $warehouseName,
        public string $locationCode,
    ) {}

    public static function fromInventory(Inventory $inventory): self
    {
        $book = $inventory->book;
        $warehouse = $inventory->warehouse;

        return new self(
            inventoryId: (int) $inventory->id,
            bookId: (int) $inventory->book_id,
            bookName: (string) ($book?->name ?? ''),
            sku: (string) ($book?->sku ?? ''),
            quantity: (int) $inventory->quantity,
            reservedQuantity: (int) $inventory->reserved_quantity,
            availableStock: $inventory->available_stock,
            warehouseName: (string) ($warehouse?->name ?? ''),
            locationCode: (string) $inventory->location_code,
        );
    }
}
