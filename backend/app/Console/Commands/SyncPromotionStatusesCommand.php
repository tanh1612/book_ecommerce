<?php

namespace App\Console\Commands;

use App\Enums\Promotion\PromotionStatus;
use App\Models\Promotion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncPromotionStatusesCommand extends Command
{
    protected $signature = 'promotions:sync-status';

    protected $description = 'Activate scheduled promotions and expire active promotions according to their time window.';

    public function handle(): int
    {
        try {
            $now = now();

            $activated = Promotion::query()
                ->where('status', PromotionStatus::SCHEDULED->value)
                ->where('start_at', '<=', $now)
                ->where('end_at', '>', $now)
                ->update(['status' => PromotionStatus::ACTIVE->value]);

            $expired = Promotion::query()
                ->whereIn('status', [
                    PromotionStatus::SCHEDULED->value,
                    PromotionStatus::ACTIVE->value,
                ])
                ->where('end_at', '<=', $now)
                ->update(['status' => PromotionStatus::EXPIRED->value]);

            $this->info("Activated {$activated} promotion(s), expired {$expired} promotion(s).");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('promotions:sync-status failed', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
