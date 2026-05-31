<?php

namespace App\Services\Ai\Dto;

readonly class GeminiGenerateContentRequest
{
    public function __construct(
        public string $userText,
        public ?string $systemInstruction = null,
    ) {}
}
