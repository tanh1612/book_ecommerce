<?php

namespace App\Services\Ai;

use App\Models\Book;
use App\Models\Inventory;
use App\Services\Ai\Dto\BookRagRetrievedDocument;
use App\Services\Ai\Dto\RetrievedBookPromptContext;
use Illuminate\Support\Facades\Log;
use Throwable;

class RetrievedBookContextLoader
{
    /**
     * @param  list<BookRagRetrievedDocument>  $documents
     * @return list<RetrievedBookPromptContext>
     */
    public function load(array $documents): array
    {
        if ($documents === []) {
            return [];
        }

        $bookIds = array_values(array_unique(array_map(
            static fn (BookRagRetrievedDocument $document): int => $document->bookId,
            $documents,
        )));

        try {
            $books = Book::query()
                ->whereIn('id', $bookIds)
                ->with([
                    'authors:id,name',
                    'categories:id,name',
                    'detail:book_id,description,publication_year,num_pages',
                    'publisher:id,name',
                    'inventories:id,book_id,quantity,reserved_quantity',
                ])
                ->get()
                ->keyBy('id');
        } catch (Throwable $e) {
            Log::error('Retrieved book context load failed', [
                'book_ids' => $bookIds,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            throw $e;
        }

        $scoreByBookId = [];
        foreach ($documents as $document) {
            $scoreByBookId[$document->bookId] = $document->score;
        }

        $contexts = [];

        foreach ($documents as $document) {
            $book = $books->get($document->bookId);
            if ($book === null) {
                continue;
            }

            $contexts[] = $this->mapBookContext($book, $scoreByBookId[$document->bookId] ?? $document->score);
        }

        return $contexts;
    }

    private function mapBookContext(Book $book, float $similarityScore): RetrievedBookPromptContext
    {
        $availableStock = (int) $book->inventories->sum(
            fn (Inventory $inventory): int => max(
                0,
                (int) $inventory->quantity - (int) $inventory->reserved_quantity,
            ),
        );

        $publicationYear = $book->detail?->publication_year;
        $numPages = $book->detail?->num_pages;

        return new RetrievedBookPromptContext(
            bookId: (int) $book->id,
            name: (string) $book->name,
            slug: (string) $book->slug,
            authorNames: $book->authors->pluck('name')->filter()->values()->all(),
            categoryNames: $book->categories->pluck('name')->filter()->values()->all(),
            descriptionShort: $this->truncateDescription((string) ($book->detail?->description ?? '')),
            publisherName: $book->publisher?->name !== null && $book->publisher->name !== ''
                ? (string) $book->publisher->name
                : null,
            publicationYear: $publicationYear !== null && (int) $publicationYear > 0
                ? (int) $publicationYear
                : null,
            numPages: $numPages !== null && (int) $numPages > 0
                ? (int) $numPages
                : null,
            sellingPrice: (float) $book->selling_price,
            averageRating: (float) $book->average_rating,
            reviewCount: (int) $book->review_count,
            availableStock: $availableStock,
            inStock: $availableStock > 0,
            similarityScore: $similarityScore,
        );
    }

    private function truncateDescription(string $description): string
    {
        $description = trim(preg_replace('/\s+/u', ' ', $description) ?? '');
        $maxChars = (int) config('ai.rag.prompt_context_max_description_chars', 500);

        if ($description === '' || mb_strlen($description) <= $maxChars) {
            return $description;
        }

        return rtrim(mb_substr($description, 0, $maxChars)).'...';
    }
}
