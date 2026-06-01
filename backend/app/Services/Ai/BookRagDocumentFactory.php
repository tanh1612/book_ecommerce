<?php

namespace App\Services\Ai;

use App\Models\Book;
use App\Services\Search\BookSearchDocumentFactory;

class BookRagDocumentFactory
{
    public function __construct(
        private readonly BookSearchDocumentFactory $catalogDocumentFactory,
    ) {}

    public function buildEmbeddingText(Book $book): string
    {
        $this->loadRelations($book);

        return $this->composeEmbeddingText($book);
    }

    /**
     * @param  array<int, float>|null  $vector
     * @return array<string, mixed>
     */
    public function makeDocument(Book $book, ?array $vector = null): array
    {
        $this->loadRelations($book);

        $document = array_merge(
            $this->catalogDocumentFactory->make($book),
            $this->ragMetadata($book),
            [
                'rag_embedding_text' => $this->composeEmbeddingText($book),
            ],
        );

        if ($vector !== null) {
            $document['_vectors'] = [
                (string) config('ai.rag.embedder_name') => $vector,
            ];
        }

        return $document;
    }

    private function loadRelations(Book $book): void
    {
        $book->loadMissing([
            'authors:id,name',
            'categories:id,name',
            'detail',
            'publisher:id,name',
            'inventories:id,book_id,quantity,reserved_quantity',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function ragMetadata(Book $book): array
    {
        $metadata = [
            'category_names' => $book->categories->pluck('name')->filter()->values()->all(),
        ];

        if ($book->publisher?->name !== null && $book->publisher->name !== '') {
            $metadata['publisher_name'] = (string) $book->publisher->name;
        }

        $detail = $book->detail;
        if ($detail === null) {
            return $metadata;
        }

        if ($detail->language !== null) {
            $metadata['language'] = $detail->language->value;
            $metadata['language_label'] = $detail->language->getLabel();
        }

        if ($detail->format !== null) {
            $metadata['format'] = $detail->format->value;
            $metadata['format_label'] = $detail->format->getLabel();
        }

        if ($detail->publication_year !== null && (int) $detail->publication_year > 0) {
            $metadata['publication_year'] = (int) $detail->publication_year;
        }

        if ($detail->num_pages !== null && (int) $detail->num_pages > 0) {
            $metadata['num_pages'] = (int) $detail->num_pages;
        }

        return $metadata;
    }

    private function composeEmbeddingText(Book $book): string
    {
        $lines = [];

        $this->appendLine($lines, 'Ten sach', $this->normalizeText((string) $book->name));

        $authorNames = $book->authors->pluck('name')->filter()->values();
        if ($authorNames->isNotEmpty()) {
            $this->appendLine($lines, 'Tac gia', $this->normalizeText($authorNames->implode(', ')));
        }

        $categoryNames = $book->categories->pluck('name')->filter()->values();
        if ($categoryNames->isNotEmpty()) {
            $this->appendLine($lines, 'The loai', $this->normalizeText($categoryNames->implode(', ')));
        }

        $description = $book->detail?->description;
        if ($description !== null && $description !== '') {
            $this->appendLine(
                $lines,
                'Mo ta',
                $this->truncateDescription($this->normalizeText((string) $description)),
            );
        }

        if ($book->publisher?->name !== null && $book->publisher->name !== '') {
            $this->appendLine($lines, 'Nha xuat ban', $this->normalizeText((string) $book->publisher->name));
        }

        if ($book->detail?->language !== null) {
            $label = $book->detail->language->getLabel();
            if ($label !== null && $label !== '') {
                $this->appendLine($lines, 'Ngon ngu', $this->normalizeText($label));
            }
        }

        if ($book->detail?->format !== null) {
            $label = $book->detail->format->getLabel();
            if ($label !== null && $label !== '') {
                $this->appendLine($lines, 'Hinh thuc', $this->normalizeText($label));
            }
        }

        $publicationYear = $book->detail?->publication_year;
        if ($publicationYear !== null && (int) $publicationYear > 0) {
            $this->appendLine($lines, 'Nam xuat ban', (string) (int) $publicationYear);
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<string>  $lines
     */
    private function appendLine(array &$lines, string $label, string $value): void
    {
        $value = trim($value);
        if ($value === '') {
            return;
        }

        $lines[] = "{$label}: {$value}";
    }

    private function normalizeText(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    private function truncateDescription(string $description): string
    {
        $maxChars = (int) config('ai.rag.embedding_text_max_description_chars', 3000);
        if (mb_strlen($description) <= $maxChars) {
            return $description;
        }

        return rtrim(mb_substr($description, 0, $maxChars));
    }
}
