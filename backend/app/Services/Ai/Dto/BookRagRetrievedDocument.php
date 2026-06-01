<?php

namespace App\Services\Ai\Dto;

readonly class BookRagRetrievedDocument
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public int $bookId,
        public float $score,
        public string $name,
        public string $slug,
        public array $raw,
    ) {}
}
