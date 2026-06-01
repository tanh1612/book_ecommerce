<?php

namespace App\Services\Ai\Dto;

readonly class ChatEvaluationResult
{
    /**
     * @param  list<string>  $riskFlags
     */
    public function __construct(
        public float $groundednessScore,
        public float $relevanceScore,
        public bool $hasHallucinationRisk,
        public string $verdict,
        public array $riskFlags,
    ) {}
}
