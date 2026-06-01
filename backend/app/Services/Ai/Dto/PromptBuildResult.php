<?php

namespace App\Services\Ai\Dto;

readonly class PromptBuildResult
{
    public function __construct(
        public string $systemInstruction,
        public string $userText,
        public bool $noRelevantContext,
    ) {}
}
