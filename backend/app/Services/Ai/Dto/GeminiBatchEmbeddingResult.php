<?php

namespace App\Services\Ai\Dto;

readonly class GeminiBatchEmbeddingResult
{
    /**
     * @param  list<array<int, float>>  $vectors
     */
    public function __construct(
        public array $vectors,
        public int $dimensions,
        public int $latencyMs,
        public string $model,
    ) {}
}
