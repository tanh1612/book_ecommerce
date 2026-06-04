<?php

namespace App\Console\Commands\Ai;

use App\Services\Ai\ChatbotIntentAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ChatbotIntentAuditCommand extends Command
{
    protected $signature = 'ai:chatbot:intent-audit {--days=7 : Look back window in days} {--limit=20 : Max rows per section}';

    protected $description = 'Audit chatbot messages for unmatched retrieval, feedback down, and no-context patterns';

    public function handle(ChatbotIntentAuditService $auditService): int
    {
        $days = max((int) $this->option('days'), 1);
        $limit = max((int) $this->option('limit'), 1);

        try {
            $report = $auditService->build($days, $limit);
        } catch (Throwable $e) {
            Log::error('Chatbot intent audit command failed', [
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            $this->error('Failed to build chatbot intent audit report.');

            return self::FAILURE;
        }

        $this->info('AI Chatbot intent audit');
        $this->line(sprintf('Since: %s', $report['since']));
        $this->newLine();

        $this->renderQuestionCountTable('Top questions with retrieval_matched=false', $report['unmatched_questions']);
        $this->renderQuestionCountTable('Top questions with feedback down', $report['feedback_down_questions']);
        $this->renderSampleList('Short questions (<=8 tokens) that hit Gemini with retrieval_matched=false', $report['short_unmatched_gemini_samples']);
        $this->renderSampleList('No-context answer samples', $report['no_context_samples']);

        return self::SUCCESS;
    }

    /**
     * @param  list<array{question: string, count: int}>  $rows
     */
    private function renderQuestionCountTable(string $title, array $rows): void
    {
        $this->comment($title);

        if ($rows === []) {
            $this->line('  (none)');
            $this->newLine();

            return;
        }

        $this->table(
            ['Question', 'Count'],
            collect($rows)->map(fn (array $row): array => [$row['question'], $row['count']])->all(),
        );

        $this->newLine();
    }

    /**
     * @param  list<string>  $samples
     */
    private function renderSampleList(string $title, array $samples): void
    {
        $this->comment($title);

        if ($samples === []) {
            $this->line('  (none)');
            $this->newLine();

            return;
        }

        foreach ($samples as $sample) {
            $this->line('  - '.$sample);
        }

        $this->newLine();
    }
}
