<?php

namespace App\Console\Commands\Ai;

use App\Services\Ai\RagVectorCoverageReporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class RagCoverageCommand extends Command
{
    protected $signature = 'ai:rag:coverage {--json : Output coverage as JSON}';

    protected $description = 'Report RAG vector coverage for active books';

    public function handle(RagVectorCoverageReporter $coverageReporter): int
    {
        try {
            $report = $coverageReporter->report();
        } catch (Throwable $e) {
            Log::error('RAG coverage command failed', [
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);
            $this->error('Failed to build RAG vector coverage report.');

            return self::FAILURE;
        }

        $output = [
            'active_books' => $report['active_books'],
            'vectorized_books' => $report['vectorized_books'],
            'missing_vectors' => $report['missing_vectors'],
            'coverage_pct' => $report['coverage_pct'],
            'index_name' => $report['index_name'],
            'embedder_name' => $report['embedder_name'],
            'embedding_dimensions' => $report['embedding_dimensions'],
        ];

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->info('AI RAG vector coverage report');
        $this->newLine();
        $this->line(sprintf('active_books: %d', $output['active_books']));
        $this->line(sprintf('vectorized_books: %d', $output['vectorized_books']));
        $this->line(sprintf('missing_vectors: %d', $output['missing_vectors']));
        $this->line(sprintf('coverage_pct: %.2f', $output['coverage_pct']));
        $this->line(sprintf('index_name: %s', $output['index_name']));
        $this->line(sprintf('embedder_name: %s', $output['embedder_name']));
        $this->line(sprintf('embedding_dimensions: %d', $output['embedding_dimensions']));

        return self::SUCCESS;
    }
}
