<?php

namespace App\Console\Commands\Recommendation;

use App\Jobs\Recommendation\BuildPopularRecommendations;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class DispatchPopularRecommendationsBuildCommand extends Command
{
    protected $signature = 'recommendations:build-popular';

    protected $description = 'Dispatch job to build popular recommendations cache';

    public function handle(): int
    {
        try {
            BuildPopularRecommendations::dispatch();
        } catch (Throwable $e) {
            Log::error('Dispatch popular recommendations build command failed', [
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            $this->error('Failed to dispatch popular recommendations build job.');

            return self::FAILURE;
        }

        $this->info('Popular recommendations build job dispatched.');

        return self::SUCCESS;
    }
}
