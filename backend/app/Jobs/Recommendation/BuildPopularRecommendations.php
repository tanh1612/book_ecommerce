<?php

namespace App\Jobs\Recommendation;

use App\Services\Recommendation\RecommendationCacheService;
use App\Services\Recommendation\RecommendationCandidateService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class BuildPopularRecommendations implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function handle(
        RecommendationCandidateService $recommendationCandidateService,
        RecommendationCacheService $recommendationCacheService,
    ): void {
        $startedAt = microtime(true);

        try {
            $bookIds = $recommendationCandidateService->buildPopularCandidateBookIds();
            $recommendationCacheService->putPopular($bookIds);

            Log::info('Build popular recommendations completed', [
                'candidate_count' => count($bookIds),
                'elapsed_ms' => (int) ((microtime(true) - $startedAt) * 1000),
            ]);
        } catch (Throwable $e) {
            Log::error('Build popular recommendations failed', [
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('BuildPopularRecommendations job failed permanently', [
            'error' => $e->getMessage(),
            'exception' => $e::class,
        ]);
    }
}
