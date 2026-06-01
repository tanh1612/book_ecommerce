<?php

namespace App\Console\Commands\Ai;

use App\Services\Ai\MeilisearchRagIndexConfigurator;
use Illuminate\Console\Command;
use Throwable;

class ConfigureMeilisearchVectorIndexCommand extends Command
{
    protected $signature = 'ai:meilisearch-configure
                            {--index= : Override Meilisearch index name}
                            {--dry-run : Print embedder payload without calling Meilisearch}';

    protected $description = 'Configure user-provided vector embedder on the books Meilisearch index for RAG';

    public function handle(MeilisearchRagIndexConfigurator $configurator): int
    {
        $indexName = (string) ($this->option('index') ?: config('ai.rag.index_name'));
        $payload = $configurator->embedderPayload();
        $catalogSearchableAttributes = $configurator->catalogSearchableAttributes();

        if ($this->option('dry-run')) {
            $this->info("Dry run — would PATCH index [{$indexName}] embedders:");
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info('Dry run — would ensure searchableAttributes are explicit (not "*") with:');
            $this->line(json_encode($catalogSearchableAttributes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        try {
            $normalized = $configurator->ensureCatalogSearchableAttributes($indexName);
            $configurator->assertRagEmbeddingTextNotSearchable($indexName);
            $configurator->configure($indexName);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $embedderName = (string) config('ai.rag.embedder_name');
        $dimensions = (int) config('ai.rag.embedding_dimensions', 768);
        $this->info("Meilisearch embedder [{$embedderName}] ({$dimensions} dimensions) configured for index [{$indexName}].");

        if ($normalized) {
            $this->info('searchableAttributes normalized to catalog-only fields: '.implode(', ', $catalogSearchableAttributes).'.');
        }

        return self::SUCCESS;
    }
}
