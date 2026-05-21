<?php

namespace App\Services\Inventory;

use App\Models\Inventory;

final class InventoryRestockResult
{
    public function __construct(
        public Inventory $inventory,
        public bool $restocked,
    ) {}
}
