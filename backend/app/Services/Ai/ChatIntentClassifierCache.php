<?php

namespace App\Services\Ai;

use App\Enums\Ai\ChatIntent;
use App\Services\Ai\Dto\ChatIntentClassificationResult;
use App\Services\Ai\Support\IntentTextNormalizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ChatIntentClassifierCache
{
    public function get(string $question): ?ChatIntentClassificationResult
    {
        try {
            $payload = Cache::get($this->cacheKey($question));

            if (! is_array($payload)) {
                return null;
            }

            $intentValue = $payload['intent'] ?? null;
            $confidence = $payload['confidence'] ?? null;
            $strategy = $payload['strategy'] ?? null;

            if (! is_string($intentValue) || ! is_numeric($confidence) || ! is_string($strategy)) {
                return null;
            }

            $chatIntent = ChatIntent::tryFrom($intentValue);

            if ($chatIntent === null) {
                return null;
            }

            return new ChatIntentClassificationResult(
                intent: $chatIntent,
                confidence: (float) $confidence,
                strategy: $strategy,
            );
        } catch (Throwable $e) {
            Log::warning('Intent classifier cache read failed', [
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return null;
        }
    }

    public function put(string $question, ChatIntentClassificationResult $result): void
    {
        if ($result->strategy === 'fallback') {
            return;
        }

        try {
            $ttl = max((int) config('ai.intent.classifier_cache_ttl_seconds', 3600), 0);

            if ($ttl === 0) {
                return;
            }

            Cache::put(
                $this->cacheKey($question),
                [
                    'intent' => $result->intent->value,
                    'confidence' => $result->confidence,
                    'strategy' => $result->strategy,
                ],
                $ttl,
            );
        } catch (Throwable $e) {
            Log::warning('Intent classifier cache write failed', [
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }
    }

    private function cacheKey(string $question): string
    {
        return 'ai:intent_classifier:'.sha1(IntentTextNormalizer::normalize($question));
    }
}
