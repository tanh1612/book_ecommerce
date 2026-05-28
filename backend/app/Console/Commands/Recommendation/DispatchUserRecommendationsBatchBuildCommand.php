<?php

namespace App\Console\Commands\Recommendation;

use App\Enums\Order\OrderStatus;
use App\Enums\Review\ReviewStatus;
use App\Jobs\Recommendation\BuildUserRecommendations;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DispatchUserRecommendationsBatchBuildCommand extends Command
{
    protected $signature = 'recommendations:build-users {--recent-days=}';

    protected $description = 'Dispatch user recommendation builds for accounts with recent recommendation signals';

    public function handle(): int
    {
        $recentDays = (int) ($this->option('recent-days') ?? config('recommendation.user_rebuild_recent_days', 7));
        $recentDays = max($recentDays, 1);
        $since = now()->subDays($recentDays);

        try {
            $accountIds = collect()
                ->merge(
                    DB::table('book_interaction_events')
                        ->where('created_at', '>=', $since)
                        ->pluck('account_id')
                )
                ->merge(
                    DB::table('wishlists')
                        ->where('updated_at', '>=', $since)
                        ->pluck('account_id')
                )
                ->merge(
                    DB::table('orders')
                        ->where('current_status', OrderStatus::COMPLETED->value)
                        ->where('updated_at', '>=', $since)
                        ->pluck('account_id')
                )
                ->merge(
                    DB::table('reviews')
                        ->where('status', ReviewStatus::APPROVED->value)
                        ->where('updated_at', '>=', $since)
                        ->pluck('account_id')
                )
                ->map(static fn ($id): int => (int) $id)
                ->filter(static fn (int $id): bool => $id > 0)
                ->unique()
                ->values();

            foreach ($accountIds as $accountId) {
                BuildUserRecommendations::dispatch($accountId);
            }
        } catch (Throwable $e) {
            Log::error('Dispatch user recommendations batch build command failed', [
                'recent_days' => $recentDays,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            $this->error('Failed to dispatch user recommendation batch build jobs.');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Dispatched %d user recommendation build job(s) for the last %d day(s).',
            $accountIds->count(),
            $recentDays,
        ));

        return self::SUCCESS;
    }
}
