<?php

namespace App\Services\Ai\Dto;

use App\Enums\Ai\ChatIntent;

readonly class ChatIntentClassificationResult
{
    public function __construct(
        public ChatIntent $intent,
        public float $confidence,
        public string $strategy,
        public ?string $reason = null,
    ) {}
}
