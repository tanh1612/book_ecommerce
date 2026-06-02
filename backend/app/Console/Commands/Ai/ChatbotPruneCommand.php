<?php

namespace App\Console\Commands\Ai;

use App\Models\AiChatMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ChatbotPruneCommand extends Command
{
    protected $signature = 'ai:chatbot:prune
                            {--days= : Retention in days (default: AI_CHAT_LOG_RETENTION_DAYS)}';

    protected $description = 'Prune ai_chat_messages older than retention; evaluations and feedback cascade via FK';

    public function handle(): int
    {
        $retentionDays = $this->resolveRetentionDays();
        $chunkSize = max((int) config('ai.operations.prune_chunk_size', 500), 1);
        $cutoff = now()->subDays($retentionDays);

        $deletedTotal = 0;

        try {
            while (true) {
                $messageIds = AiChatMessage::query()
                    ->where('created_at', '<', $cutoff)
                    ->orderBy('id')
                    ->limit($chunkSize)
                    ->pluck('id');

                if ($messageIds->isEmpty()) {
                    break;
                }

                $deletedTotal += AiChatMessage::query()
                    ->whereIn('id', $messageIds)
                    ->delete();
            }
        } catch (Throwable $e) {
            Log::error('Chatbot prune command failed', [
                'retention_days' => $retentionDays,
                'chunk_size' => $chunkSize,
                'deleted_so_far' => $deletedTotal,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            $this->error('Failed to prune old chat messages.');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Pruned %d chat message(s) older than %d day(s) (cutoff: %s).',
            $deletedTotal,
            $retentionDays,
            $cutoff->toDateTimeString(),
        ));

        return self::SUCCESS;
    }

    private function resolveRetentionDays(): int
    {
        $daysOption = $this->option('days');

        if ($daysOption !== null && $daysOption !== '') {
            return max((int) $daysOption, 1);
        }

        return max((int) config('ai.operations.log_retention_days', 90), 1);
    }
}
