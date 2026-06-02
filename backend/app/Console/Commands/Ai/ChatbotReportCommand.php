<?php

namespace App\Console\Commands\Ai;

use App\Services\Ai\ChatbotOperationsReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ChatbotReportCommand extends Command
{
    protected $signature = 'ai:chatbot:report';

    protected $description = 'Print chatbot operations metrics (messages, retrieval, errors, evaluation, feedback)';

    public function handle(ChatbotOperationsReportService $reportService): int
    {
        try {
            $report = $reportService->build();
        } catch (Throwable $e) {
            Log::error('Chatbot report command failed', [
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            $this->error('Failed to build chatbot operations report.');

            return self::FAILURE;
        }

        $this->info('AI Chatbot operations report');
        $this->newLine();

        $this->line(sprintf('Messages (24h): %d', $report['messages_24h']));
        $this->line(sprintf('Messages (7d): %d', $report['messages_7d']));

        $matchedRate = $report['retrieval_matched_rate_7d'];
        $this->line(sprintf(
            'Retrieval matched rate (7d): %s',
            $matchedRate === null ? 'n/a' : sprintf('%.2f%%', $matchedRate),
        ));

        $this->newLine();
        $this->renderCountTable('Retrieval strategy (7d)', $report['retrieval_strategy_7d']);
        $this->renderCountTable('Gemini errors by error_code (7d)', $report['gemini_errors_7d']);
        $this->renderCountTable('Evaluation verdict (7d)', $report['evaluation_verdict_7d']);

        $this->line(sprintf('Hallucination risk count (7d): %d', $report['hallucination_risk_count_7d']));
        $this->line(sprintf('Feedback up (7d): %d', $report['feedback_up_7d']));
        $this->line(sprintf('Feedback down (7d): %d', $report['feedback_down_7d']));

        $latency = $report['avg_latency_ms_7d'];
        $tokens = $report['avg_token_total_7d'];
        $this->line(sprintf(
            'Avg latency ms (7d): %s',
            $latency === null ? 'n/a' : (string) $latency,
        ));
        $this->line(sprintf(
            'Avg token total (7d): %s',
            $tokens === null ? 'n/a' : (string) $tokens,
        ));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, int>  $rows
     */
    private function renderCountTable(string $title, array $rows): void
    {
        $this->comment($title);

        if ($rows === []) {
            $this->line('  (none)');

            $this->newLine();

            return;
        }

        $this->table(
            ['Key', 'Count'],
            collect($rows)->map(fn (int $count, string $key) => [$key, $count])->values()->all(),
        );

        $this->newLine();
    }
}
