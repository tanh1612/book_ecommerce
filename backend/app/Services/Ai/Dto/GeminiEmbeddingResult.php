<?php

namespace App\Services\Ai\Dto;

readonly class GeminiEmbeddingResult
{
    /**
     * @param  array<int, float>  $vector
     */
    public function __construct(
        public array $vector,
        public int $dimensions,
        public int $latencyMs,
        public string $model,
    ) {}
}
