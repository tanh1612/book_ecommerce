<?php

namespace App\Jobs\Recommendation;

use App\Services\Recommendation\RecommendationCacheService;
use App\Services\Recommendation\RecommendationCandidateService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class BuildUserRecommendations implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public int $uniqueFor = 900;

    public function __construct(
        public int $accountId,
    ) {}

    public function handle(
        RecommendationCandidateService $recommendationCandidateService,
        RecommendationCacheService $recommendationCacheService,
    ): void {
        $startedAt = microtime(true);

        try {
            $bookIds = $recommendationCandidateService->buildUserCandidateBookIds($this->accountId);

            if ($bookIds === []) {
                $recommendationCacheService->forgetUser($this->accountId);

                Log::info('Build user recommendations skipped due to insufficient signals', [
                    'account_id' => $this->accountId,
                    'elapsed_ms' => (int) ((microtime(true) - $startedAt) * 1000),
                ]);

                return;
            }

            $recommendationCacheService->putUser($this->accountId, $bookIds, 'content_based');

            Log::info('Build user recommendations completed', [
                'account_id' => $this->accountId,
                'candidate_count' => count($bookIds),
                'elapsed_ms' => (int) ((microtime(true) - $startedAt) * 1000),
            ]);
        } catch (Throwable $e) {
            Log::error('Build user recommendations failed', [
                'account_id' => $this->accountId,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            throw $e;
        }
    }

    public function uniqueId(): string
    {
        return sprintf('recommendation:user:%d', $this->accountId);
    }

    public function failed(Throwable $e): void
    {
        Log::error('BuildUserRecommendations job failed permanently', [
            'account_id' => $this->accountId,
            'error' => $e->getMessage(),
            'exception' => $e::class,
        ]);
    }
}
