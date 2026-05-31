<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Log;
use Meilisearch\Client;
use Throwable;

class MeilisearchRagDocumentWriter
{
    public function __construct(
        private readonly ?Client $client = null,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $documents
     */
    public function upsertMany(array $documents): void
    {
        if ($documents === []) {
            return;
        }

        $indexName = (string) config('ai.rag.index_name');

        try {
            $task = $this->client()->index($indexName)->addDocuments($documents);
            $this->waitForTask($task);
        } catch (Throwable $e) {
            Log::error('Meilisearch RAG documents bulk upsert failed', [
                'index' => $indexName,
                'document_count' => count($documents),
                'book_ids' => array_values(array_filter(array_map(
                    static fn (array $document): ?int => isset($document['id']) ? (int) $document['id'] : null,
                    $documents,
                ))),
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $document
     */
    public function upsert(array $document): void
    {
        $indexName = (string) config('ai.rag.index_name');

        try {
            $task = $this->client()->index($indexName)->addDocuments([$document]);
            $this->waitForTask($task);
        } catch (Throwable $e) {
            Log::error('Meilisearch RAG document upsert failed', [
                'index' => $indexName,
                'book_id' => $document['id'] ?? null,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            throw $e;
        }
    }

    /**
     * @return array<int, float>|null
     */
    public function getDocumentVectors(int $bookId): ?array
    {
        $indexName = (string) config('ai.rag.index_name');
        $embedderName = (string) config('ai.rag.embedder_name');

        try {
            $document = $this->client()->index($indexName)->getDocument((string) $bookId);
            $vectors = $document['_vectors'][$embedderName] ?? null;

            return is_array($vectors) ? array_values($vectors) : null;
        } catch (Throwable $e) {
            Log::debug('Meilisearch RAG document vectors not found', [
                'index' => $indexName,
                'book_id' => $bookId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<int, float>  $vector
     */
    public function upsertVectors(int $bookId, array $vector): void
    {
        $indexName = (string) config('ai.rag.index_name');
        $embedderName = (string) config('ai.rag.embedder_name');

        try {
            $task = $this->client()->index($indexName)->updateDocuments([
                [
                    'id' => $bookId,
                    '_vectors' => [
                        $embedderName => $vector,
                    ],
                ],
            ]);
            $this->waitForTask($task);
        } catch (Throwable $e) {
            Log::error('Meilisearch RAG vector upsert failed', [
                'index' => $indexName,
                'book_id' => $bookId,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            throw $e;
        }
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
    private function waitForTask(array $task): void
    {
        $taskUid = $task['taskUid'] ?? $task['uid'] ?? null;

        if ($taskUid === null) {
            return;
        }

        try {
            $this->client()->waitForTask((int) $taskUid);
        } catch (Throwable $e) {
            Log::error('Meilisearch wait for RAG document task failed', [
                'task_uid' => $taskUid,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            throw $e;
        }
    }
}
