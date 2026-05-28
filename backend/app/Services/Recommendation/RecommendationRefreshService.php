<?php

namespace App\Services\Recommendation;

use App\Jobs\Recommendation\BuildUserRecommendations;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecommendationRefreshService
{
    public function __construct(
        private RecommendationCacheService $recommendationCacheService,
    ) {}

    public function refreshUserRecommendations(int $accountId, string $reason): void
    {
        if ($accountId <= 0) {
            return;
        }

        $this->recommendationCacheService->forgetUser($accountId);
        $debounceSeconds = max((int) config('recommendation.user_refresh_debounce_seconds', 120), 1);
        $debounceKey = sprintf('reco:refresh:user:%d:debounce', $accountId);

        $shouldDispatch = true;
        try {
            $shouldDispatch = Cache::add($debounceKey, now()->toIso8601String(), $debounceSeconds);
        } catch (Throwable $e) {
            Log::warning('Recommendation refresh debounce failed', [
                'account_id' => $accountId,
                'reason' => $reason,
                'debounce_key' => $debounceKey,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }

        if (! $shouldDispatch) {
            return;
        }

        try {
            BuildUserRecommendations::dispatch($accountId);
        } catch (Throwable $e) {
            Log::error('Recommendation refresh dispatch failed', [
                'account_id' => $accountId,
                'reason' => $reason,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }
    }
}
