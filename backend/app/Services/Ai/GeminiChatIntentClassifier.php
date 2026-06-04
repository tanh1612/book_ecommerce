<?php

namespace App\Services\Ai;

use App\Enums\Ai\ChatIntent;
use App\Services\Ai\Contracts\ChatIntentClassifier;
use App\Services\Ai\Dto\ChatIntentClassificationResult;
use Illuminate\Support\Facades\Log;
use Throwable;

class GeminiChatIntentClassifier implements ChatIntentClassifier
{
    public function __construct(
        private readonly GeminiClient $geminiClient,
        private readonly ChatIntentClassifierCache $cache,
    ) {}

    public function classify(string $question): ChatIntentClassificationResult
    {
        $cached = $this->cache->get($question);

        if ($cached !== null) {
            return $cached;
        }

        try {
            $chatResult = $this->geminiClient->generateIntentClassification($question);
            $parsed = $this->parseClassificationJson($chatResult->text);
            $this->cache->put($question, $parsed);

            return $parsed;
        } catch (Throwable $e) {
            Log::warning('Gemini intent classification failed', [
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return $this->fallbackResult();
        }
    }

    /**
     * @return ChatIntentClassificationResult
     */
    private function parseClassificationJson(string $rawText): ChatIntentClassificationResult
    {
        $decoded = json_decode($this->extractJsonObject($rawText), true);

        if (! is_array($decoded)) {
            return $this->fallbackResult();
        }

        $intentValue = $decoded['intent'] ?? null;
        $confidence = $decoded['confidence'] ?? null;

        if (! is_string($intentValue)) {
            return $this->fallbackResult();
        }

        $intent = ChatIntent::tryFrom($intentValue);

        if ($intent === null) {
            return $this->fallbackResult();
        }

        if (! is_numeric($confidence)) {
            return $this->fallbackResult();
        }

        $confidenceFloat = (float) $confidence;

        if ($confidenceFloat < 0.0 || $confidenceFloat > 1.0) {
            return $this->fallbackResult();
        }

        return new ChatIntentClassificationResult(
            intent: $intent,
            confidence: $confidenceFloat,
            strategy: 'llm',
        );
    }

    private function extractJsonObject(string $rawText): string
    {
        $trimmed = trim($rawText);

        if (str_starts_with($trimmed, '{')) {
            return $trimmed;
        }

        if (preg_match('/\{.*\}/s', $trimmed, $matches) === 1) {
            return $matches[0];
        }

        return $trimmed;
    }

    private function fallbackResult(): ChatIntentClassificationResult
    {
        return new ChatIntentClassificationResult(
            intent: ChatIntent::Unknown,
            confidence: 0.0,
            strategy: 'fallback',
        );
    }
}
