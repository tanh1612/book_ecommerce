<?php

namespace App\Services\Ai;

use App\Exceptions\Ai\GeminiClientException;
use App\Services\Ai\Dto\GeminiChatResult;
use App\Services\Ai\Dto\GeminiEmbeddingResult;
use App\Services\Ai\Dto\GeminiGenerateContentRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GeminiClient
{
    public function embedText(string $text): GeminiEmbeddingResult
    {
        $this->ensureApiKey();

        $model = (string) config('ai.gemini.embedding_model');
        $dimensions = (int) config('ai.rag.embedding_dimensions', 768);
        $startedAt = hrtime(true);

        $response = $this->requestWithRetry(
            operation: 'embed',
            model: $model,
            method: 'post',
            url: $this->modelPath($model, 'embedContent'),
            payload: [
                'content' => [
                    'parts' => [
                        ['text' => $text],
                    ],
                ],
                'outputDimensionality' => $dimensions,
            ],
            startedAt: $startedAt,
        );

        $latencyMs = $this->elapsedMilliseconds($startedAt);
        $vector = $this->parseEmbeddingVector($response->json(), $dimensions, $latencyMs);

        $this->logSuccess('embed', $model, $startedAt, [
            'embedding_dimensions' => $dimensions,
        ]);

        return new GeminiEmbeddingResult(
            vector: $vector,
            dimensions: $dimensions,
            latencyMs: $latencyMs,
            model: $model,
        );
    }

    public function generateAnswer(GeminiGenerateContentRequest $request): GeminiChatResult
    {
        $this->ensureApiKey();

        $model = (string) config('ai.gemini.chat_model');
        $startedAt = hrtime(true);

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $request->userText],
                    ],
                ],
            ],
        ];

        if ($request->systemInstruction !== null && $request->systemInstruction !== '') {
            $payload['systemInstruction'] = [
                'parts' => [
                    ['text' => $request->systemInstruction],
                ],
            ];
        }

        $response = $this->requestWithRetry(
            operation: 'chat',
            model: $model,
            method: 'post',
            url: $this->modelPath($model, 'generateContent'),
            payload: $payload,
            startedAt: $startedAt,
        );

        $latencyMs = $this->elapsedMilliseconds($startedAt);
        $result = $this->parseChatResponse($response->json(), $model, $latencyMs);

        $context = [];
        if ($result->tokenUsage !== null) {
            $context['token_usage'] = $result->tokenUsage;
        }

        $this->logSuccess('chat', $model, $startedAt, $context);

        return $result;
    }

    private function ensureApiKey(): void
    {
        $apiKey = config('ai.gemini.api_key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new GeminiClientException(
                'Gemini API key is not configured',
                GeminiClientException::MISSING_API_KEY,
            );
        }
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('ai.gemini.base_url'), '/'))
            ->timeout(max((int) config('ai.gemini.timeout_seconds', 15), 1))
            ->acceptJson()
            ->withHeaders([
                'x-goog-api-key' => (string) config('ai.gemini.api_key'),
            ]);
    }

    private function modelPath(string $model, string $action): string
    {
        return sprintf('/models/%s:%s', $model, $action);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function requestWithRetry(
        string $operation,
        string $model,
        string $method,
        string $url,
        array $payload,
        int $startedAt,
    ): Response {
        $maxAttempts = max((int) config('ai.gemini.retry_times', 2), 0) + 1;
        $sleepMs = max((int) config('ai.gemini.retry_sleep_ms', 200), 0);
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = $this->http()->{$method}($url, $payload);

                if ($response->successful()) {
                    return $response;
                }

                if (! $this->shouldRetryStatus($response->status()) || $attempt >= $maxAttempts) {
                    $this->throwForResponse($operation, $model, $response, $startedAt);
                }

                $this->logRetry($operation, $model, $response->status(), $attempt, $startedAt);
            } catch (ConnectionException $e) {
                $lastException = $e;

                if ($attempt >= $maxAttempts) {
                    $this->logFailure($operation, $model, GeminiClientException::TIMEOUT, null, $startedAt, $e);

                    throw new GeminiClientException(
                        'Gemini API connection failed',
                        GeminiClientException::TIMEOUT,
                        latencyMs: $this->elapsedMilliseconds($startedAt),
                        previous: $e,
                    );
                }

                $this->logRetry($operation, $model, null, $attempt, $startedAt, $e);
            } catch (GeminiClientException $e) {
                throw $e;
            } catch (Throwable $e) {
                $this->logFailure($operation, $model, GeminiClientException::API_ERROR, null, $startedAt, $e);

                throw new GeminiClientException(
                    'Gemini API request failed',
                    GeminiClientException::API_ERROR,
                    latencyMs: $this->elapsedMilliseconds($startedAt),
                    previous: $e,
                );
            }

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        $this->logFailure($operation, $model, GeminiClientException::TIMEOUT, null, $startedAt, $lastException);

        throw new GeminiClientException(
            'Gemini API connection failed',
            GeminiClientException::TIMEOUT,
            latencyMs: $this->elapsedMilliseconds($startedAt),
            previous: $lastException,
        );
    }

    private function shouldRetryStatus(int $status): bool
    {
        return $status === 429 || $status >= 500;
    }

    private function throwForResponse(string $operation, string $model, Response $response, int $startedAt): never
    {
        $status = $response->status();
        $errorCode = match (true) {
            $status === 429 => GeminiClientException::RATE_LIMIT,
            $status >= 500 => GeminiClientException::SERVER_ERROR,
            default => GeminiClientException::API_ERROR,
        };

        $this->logFailure($operation, $model, $errorCode, $status, $startedAt);

        throw new GeminiClientException(
            sprintf('Gemini API returned HTTP %d', $status),
            $errorCode,
            httpStatus: $status,
            latencyMs: $this->elapsedMilliseconds($startedAt),
        );
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @return array<int, float>
     */
    private function parseEmbeddingVector(?array $body, int $expectedDimensions, int $latencyMs): array
    {
        $values = $body['embedding']['values'] ?? null;

        if (! is_array($values) || $values === []) {
            throw new GeminiClientException(
                'Gemini embedding response is missing vector values',
                GeminiClientException::INVALID_RESPONSE,
                latencyMs: $latencyMs,
            );
        }

        $vector = array_map(static fn ($value): float => (float) $value, $values);

        if (count($vector) !== $expectedDimensions) {
            throw new GeminiClientException(
                sprintf('Gemini embedding dimension mismatch: expected %d, got %d', $expectedDimensions, count($vector)),
                GeminiClientException::INVALID_RESPONSE,
                latencyMs: $latencyMs,
            );
        }

        return $vector;
    }

    /**
     * @param  array<string, mixed>|null  $body
     */
    private function parseChatResponse(?array $body, string $model, int $latencyMs): GeminiChatResult
    {
        $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (! is_string($text) || trim($text) === '') {
            throw new GeminiClientException(
                'Gemini chat response is missing answer text',
                GeminiClientException::INVALID_RESPONSE,
                latencyMs: $latencyMs,
            );
        }

        $usage = $body['usageMetadata'] ?? null;
        $tokenUsage = null;

        if (is_array($usage)) {
            $tokenUsage = [
                'prompt' => (int) ($usage['promptTokenCount'] ?? 0),
                'candidates' => (int) ($usage['candidatesTokenCount'] ?? 0),
                'total' => (int) ($usage['totalTokenCount'] ?? 0),
            ];
        }

        return new GeminiChatResult(
            text: trim($text),
            model: $model,
            latencyMs: $latencyMs,
            tokenUsage: $tokenUsage,
        );
    }

    private function elapsedMilliseconds(int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logSuccess(
        string $operation,
        string $model,
        int $startedAt,
        array $context = [],
    ): void {
        Log::info('Gemini API call succeeded', array_merge([
            'operation' => $operation,
            'model' => $model,
            'latency_ms' => $this->elapsedMilliseconds($startedAt),
        ], $context));
    }

    private function logRetry(
        string $operation,
        string $model,
        ?int $httpStatus,
        int $attempt,
        int $startedAt,
        ?Throwable $previous = null,
    ): void {
        Log::warning('Gemini API retry', [
            'operation' => $operation,
            'model' => $model,
            'attempt' => $attempt,
            'http_status' => $httpStatus,
            'latency_ms' => $this->elapsedMilliseconds($startedAt),
            'error' => $previous?->getMessage(),
        ]);
    }

    private function logFailure(
        string $operation,
        string $model,
        string $errorCode,
        ?int $httpStatus,
        int $startedAt,
        ?Throwable $previous = null,
    ): void {
        $context = [
            'operation' => $operation,
            'model' => $model,
            'error_code' => $errorCode,
            'http_status' => $httpStatus,
            'latency_ms' => $this->elapsedMilliseconds($startedAt),
        ];

        if ($previous !== null) {
            $context['error'] = $previous->getMessage();
            $context['exception'] = $previous::class;
        }

        Log::warning('Gemini API call failed', $context);
    }
}
