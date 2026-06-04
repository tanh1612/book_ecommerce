<?php

namespace App\Services\Ai\Contracts;

use App\Services\Ai\Dto\ChatIntentClassificationResult;

interface ChatIntentClassifier
{
    public function classify(string $question): ChatIntentClassificationResult;
}
