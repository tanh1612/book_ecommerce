<?php

namespace App\Services\Inventory;

use App\Enums\Account\AccountRole;
use App\Enums\Inventory\InventoryStockAlertType;
use App\Models\Account;
use App\Models\Inventory;
use App\Notifications\Inventory\InventoryStockStatusNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class InventoryStockStatusNotifier
{
    public function notify(Inventory $inventory, InventoryStockAlertType $type): void
    {
        try {
            $inventory->loadMissing(['book', 'warehouse']);

            if ($type === InventoryStockAlertType::LowStock && ! ($inventory->book?->is_active ?? false)) {
                return;
            }

            $admins = Account::query()
                ->where('role', AccountRole::Admin)
                ->where('is_active', true)
                ->get();

            if ($admins->isEmpty()) {
                return;
            }

            Notification::send($admins, new InventoryStockStatusNotification($inventory, $type));
        } catch (\Throwable $e) {
            Log::error('Inventory stock status notification failed', [
                'inventory_id' => $inventory->id,
                'alert_type' => $type->value,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
