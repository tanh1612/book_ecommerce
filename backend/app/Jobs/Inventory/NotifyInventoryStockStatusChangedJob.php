<?php

namespace App\Jobs\Inventory;

use App\Enums\Inventory\InventoryStockAlertType;
use App\Models\Inventory;
use App\Services\Inventory\InventoryStockStatusNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class NotifyInventoryStockStatusChangedJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $inventoryId,
        public InventoryStockAlertType $alertType,
    ) {}

    public function handle(InventoryStockStatusNotifier $notifier): void
    {
        $inventory = Inventory::query()
            ->with(['book', 'warehouse'])
            ->find($this->inventoryId);

        if ($inventory === null) {
            return;
        }

        $notifier->notify($inventory, $this->alertType);
    }
}
