<?php

namespace App\Services\Ai\Dto;

readonly class OutOfScopeIntentGuardResult
{
    public function __construct(
        public bool $matched,
        public ?string $category,
        public ?string $response,
    ) {}
}
