<?php

namespace App\Services\Ai;

use App\Models\Book;
use Illuminate\Support\Facades\Log;
use Meilisearch\Client;
use RuntimeException;
use Throwable;

class MeilisearchRagIndexConfigurator
{
    public function __construct(
        private readonly ?Client $client = null,
    ) {}

    /**
     * @return array<string, array{source: string, dimensions: int}>
     */
    public function embedderPayload(?string $embedderName = null, ?int $dimensions = null): array
    {
        $embedderName ??= (string) config('ai.rag.embedder_name');
        $dimensions ??= (int) config('ai.rag.embedding_dimensions', 768);

        return [
            $embedderName => [
                'source' => 'userProvided',
                'dimensions' => $dimensions,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public function catalogSearchableAttributes(): array
    {
        $settings = config('scout.meilisearch.index-settings.'.Book::class.'.searchableAttributes');

        if (is_array($settings) && $settings !== []) {
            return array_values($settings);
        }

        return ['name', 'author_names', 'description'];
    }

    public function ensureCatalogSearchableAttributes(?string $indexName = null): bool
    {
        $indexName ??= (string) config('ai.rag.index_name');
        $current = $this->searchableAttributes($indexName);

        if (! $this->searchableAttributesExposeRagEmbeddingText($current)) {
            return false;
        }

        $expected = $this->catalogSearchableAttributes();

        try {
            $task = $this->client()->index($indexName)->updateSearchableAttributes($expected);
            $this->waitForSettingsTask($task);
        } catch (Throwable $e) {
            Log::error('Meilisearch update searchable attributes failed', [
                'index' => $indexName,
                'attributes' => $expected,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            throw $e;
        }

        Log::info('Meilisearch searchable attributes normalized for RAG index', [
            'index' => $indexName,
            'previous' => $current,
            'attributes' => $expected,
        ]);

        return true;
    }

    public function assertRagEmbeddingTextNotSearchable(?string $indexName = null): void
    {
        $indexName ??= (string) config('ai.rag.index_name');
        $attributes = $this->searchableAttributes($indexName);

        if (in_array('*', $attributes, true)) {
            throw new RuntimeException(
                "searchableAttributes must not use wildcard '*' for index {$indexName}; rag_embedding_text would be keyword-searchable.",
            );
        }

        if (in_array('rag_embedding_text', $attributes, true)) {
            throw new RuntimeException(
                "rag_embedding_text must not be listed in searchableAttributes for index {$indexName}.",
            );
        }
    }

    public function configure(?string $indexName = null): void
    {
        $indexName ??= (string) config('ai.rag.index_name');

        try {
            $this->client()->index($indexName)->updateEmbedders($this->embedderPayload());
        } catch (Throwable $e) {
            Log::error('Meilisearch RAG embedder configure failed', [
                'index' => $indexName,
                'embedder' => config('ai.rag.embedder_name'),
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            throw $e;
        }
    }

    /**
     * @return list<string>
     */
    public function searchableAttributes(?string $indexName = null): array
    {
        $indexName ??= (string) config('ai.rag.index_name');

        try {
            $attributes = $this->client()->index($indexName)->getSearchableAttributes();

            return is_array($attributes) ? array_values($attributes) : [];
        } catch (Throwable $e) {
            Log::error('Meilisearch get searchable attributes failed', [
                'index' => $indexName,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            throw $e;
        }
    }

    /**
     * @param  list<string>  $attributes
     */
    private function searchableAttributesExposeRagEmbeddingText(array $attributes): bool
    {
        return in_array('*', $attributes, true)
            || in_array('rag_embedding_text', $attributes, true);
    }

    private function client(): Client
    {
        if ($this->client !== null) {
            return $this->client;
        }

        return new Client(
            (string) config('scout.meilisearch.host'),
            config('scout.meilisearch.key'),
        );
    }

    /**
     * @param  array<string, mixed>  $task
     */
    private function waitForSettingsTask(array $task): void
    {
        $taskUid = $task['taskUid'] ?? $task['uid'] ?? null;

        if ($taskUid === null) {
            return;
        }

        try {
            $this->client()->waitForTask((int) $taskUid);
        } catch (Throwable $e) {
            Log::error('Meilisearch wait for searchable attributes task failed', [
                'task_uid' => $taskUid,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            throw $e;
        }
    }
}
