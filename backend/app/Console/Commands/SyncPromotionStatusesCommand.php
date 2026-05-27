<?php

namespace App\Console\Commands;

use App\Services\Promotion\PromotionLifecycleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncPromotionStatusesCommand extends Command
{
    protected $signature = 'promotions:sync-status';

    protected $description = 'Activate scheduled promotions and expire active promotions according to their time window.';

    public function handle(PromotionLifecycleService $lifecycle): int
    {
        try {
            $result = $lifecycle->syncStatuses();

            $this->info("Activated {$result['activated']} promotion(s), expired {$result['expired']} promotion(s).");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('promotions:sync-status failed', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
