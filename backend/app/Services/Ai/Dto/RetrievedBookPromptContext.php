<?php

namespace App\Services\Ai\Dto;

readonly class RetrievedBookPromptContext
{
    /**
     * @param  list<string>  $authorNames
     * @param  list<string>  $categoryNames
     */
    public function __construct(
        public int $bookId,
        public string $name,
        public string $slug,
        public array $authorNames,
        public array $categoryNames,
        public string $descriptionShort,
        public ?string $publisherName,
        public ?int $publicationYear,
        public ?int $numPages,
        public float $sellingPrice,
        public float $averageRating,
        public int $reviewCount,
        public int $availableStock,
        public bool $inStock,
        public float $similarityScore,
    ) {}
}
