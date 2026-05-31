<?php

namespace App\Services\Ai\Dto;

readonly class GeminiChatResult
{
    /**
     * @param  array{prompt: int, candidates: int, total: int}|null  $tokenUsage
     */
    public function __construct(
        public string $text,
        public string $model,
        public int $latencyMs,
        public ?array $tokenUsage = null,
    ) {}
}
