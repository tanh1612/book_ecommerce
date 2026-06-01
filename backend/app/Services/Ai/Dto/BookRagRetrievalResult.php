<?php

namespace App\Services\Ai\Dto;

readonly class BookRagRetrievalResult
{
    /**
     * @param  list<BookRagRetrievedDocument>  $documents
     */
    public function __construct(
        public bool $matched,
        public ?float $topScore,
        public array $documents,
        public string $strategy,
        public ?int $embeddingLatencyMs = null,
        public ?int $searchLatencyMs = null,
    ) {}
}
