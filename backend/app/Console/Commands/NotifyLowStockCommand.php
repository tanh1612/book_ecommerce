<?php

namespace App\Console\Commands;

use App\Enums\Account\AccountRole;
use App\Models\Account;
use App\Notifications\Inventory\LowStockBooksNotification;
use App\Services\Inventory\LowStockAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class NotifyLowStockCommand extends Command
{
    protected $signature = 'inventory:notify-low-stock';

    protected $description = 'Notify active admins about books with low available stock.';

    public function handle(LowStockAlertService $lowStockAlertService): int
    {
        try {
            $items = $lowStockAlertService->getLowStockItems();

            if ($items->isEmpty()) {
                $this->info('No low-stock inventories found.');

                return self::SUCCESS;
            }

            $inventoryIds = $lowStockAlertService->lowStockInventoryIds();
            $hash = $lowStockAlertService->lowStockSetHash($inventoryIds);
            $cacheKey = 'low_stock_alert:'.now()->toDateString().':'.$hash;

            if (Cache::has($cacheKey)) {
                $this->info('Low-stock alert already sent today for the same inventory set.');

                return self::SUCCESS;
            }

            $admins = Account::query()
                ->where('role', AccountRole::Admin)
                ->where('is_active', true)
                ->get();

            if ($admins->isEmpty()) {
                $this->warn('No active admin accounts to notify.');

                return self::SUCCESS;
            }

            Notification::send($admins, new LowStockBooksNotification($items));

            Cache::put($cacheKey, true, now()->endOfDay());

            $this->info("Sent low-stock alert to {$admins->count()} admin(s) for {$items->count()} inventory row(s).");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('inventory:notify-low-stock failed', [
                'error' => $e->getMessage(),
            ]);

            $this->error('Low-stock notification failed. See application log for details.');

            return self::FAILURE;
        }
    }
}
