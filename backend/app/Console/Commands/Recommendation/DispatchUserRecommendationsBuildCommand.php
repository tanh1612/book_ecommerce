<?php

namespace App\Console\Commands\Recommendation;

use App\Jobs\Recommendation\BuildUserRecommendations;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class DispatchUserRecommendationsBuildCommand extends Command
{
    protected $signature = 'recommendations:build-user {account_id : Target account id}';

    protected $description = 'Dispatch job to build personalized recommendations for one account';

    public function handle(): int
    {
        $accountId = (int) $this->argument('account_id');
        if ($accountId <= 0) {
            $this->error('account_id must be a positive integer.');

            return self::INVALID;
        }

        try {
            BuildUserRecommendations::dispatch($accountId);
        } catch (Throwable $e) {
            Log::error('Dispatch user recommendations build command failed', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            $this->error('Failed to dispatch user recommendations build job.');

            return self::FAILURE;
        }

        $this->info(sprintf('User recommendations build job dispatched for account_id=%d.', $accountId));

        return self::SUCCESS;
    }
}
