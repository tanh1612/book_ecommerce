<?php

namespace App\Console\Commands\Recommendation;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PruneBookInteractionEventsCommand extends Command
{
    protected $signature = 'recommendations:prune-interactions';

    protected $description = 'Prune old book interaction events based on retention policy';

    public function handle(): int
    {
        $retentionDays = max((int) config('recommendation.interaction_retention_days', 180), 1);
        $cutoff = now()->subDays($retentionDays);

        try {
            $deletedRows = DB::table('book_interaction_events')
                ->where('created_at', '<', $cutoff)
                ->delete();
        } catch (Throwable $e) {
            Log::error('Prune book interaction events command failed', [
                'retention_days' => $retentionDays,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            $this->error('Failed to prune old interaction events.');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Pruned %d interaction event(s) older than %d day(s).',
            $deletedRows,
            $retentionDays,
        ));

        return self::SUCCESS;
    }
}
