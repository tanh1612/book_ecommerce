<?php

namespace App\Services\Ai;

use App\Models\Book;
use Illuminate\Support\Facades\Log;
use Throwable;

class RagVectorCoverageReporter
{
    public function __construct(
        private readonly MeilisearchRagDocumentWriter $documentWriter,
    ) {}

    /**
     * @return array{
     *     active_books: int,
     *     vectorized_books: int,
     *     missing_vectors: int,
     *     coverage_pct: float,
     *     index_name: string,
     *     embedder_name: string,
     *     embedding_dimensions: int
     * }
     */
    public function report(): array
    {
        $activeBooks = 0;
        $vectorizedBooks = 0;

        try {
            foreach (Book::query()->active()->orderBy('id')->cursor() as $book) {
                $activeBooks++;

                if ($this->documentWriter->getDocumentVectors((int) $book->id) !== null) {
                    $vectorizedBooks++;
                }
            }
        } catch (Throwable $e) {
            Log::error('RAG vector coverage report failed', [
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            throw $e;
        }

        $coveragePct = $activeBooks > 0
            ? round(($vectorizedBooks / $activeBooks) * 100, 2)
            : 0.0;

        return [
            'active_books' => $activeBooks,
            'vectorized_books' => $vectorizedBooks,
            'missing_vectors' => max(0, $activeBooks - $vectorizedBooks),
            'coverage_pct' => $coveragePct,
            'index_name' => (string) config('ai.rag.index_name'),
            'embedder_name' => (string) config('ai.rag.embedder_name'),
            'embedding_dimensions' => (int) config('ai.rag.embedding_dimensions'),
        ];
    }
}
