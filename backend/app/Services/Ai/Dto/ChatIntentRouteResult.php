<?php

namespace App\Services\Ai\Dto;

use App\Enums\Ai\ChatIntent;

readonly class ChatIntentRouteResult
{
    public function __construct(
        public ChatIntent $intent,
        public bool $shouldShortCircuit,
        public ?string $response,
        public float $confidence,
        public string $strategy,
    ) {}
}
