<?php

use App\Exceptions\Ai\GeminiClientException;
use App\Services\Ai\Dto\GeminiGenerateContentRequest;
use App\Services\Ai\GeminiClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    config([
        'ai.gemini.api_key' => 'test-api-key',
        'ai.gemini.timeout_seconds' => 5,
        'ai.gemini.retry_times' => 2,
        'ai.gemini.retry_sleep_ms' => 0,
        'ai.rag.embedding_dimensions' => 3,
    ]);

    Http::preventStrayRequests();
});

function geminiClient(): GeminiClient
{
    return app(GeminiClient::class);
}

test('embedText returns vector with configured dimensionality', function (): void {
    Http::fake([
        '*:embedContent*' => Http::response([
            'embedding' => [
                'values' => [0.1, 0.2, 0.3],
            ],
        ], 200),
    ]);

    $result = geminiClient()->embedText('Toi muon tim sach');

    expect($result->vector)->toBe([0.1, 0.2, 0.3])
        ->and($result->dimensions)->toBe(3)
        ->and($result->model)->toBe(config('ai.gemini.embedding_model'));

    Http::assertSent(function ($request): bool {
        $body = $request->data();

        return str_contains($request->url(), ':embedContent')
            && ! str_contains($request->url(), 'key=')
            && $request->hasHeader('x-goog-api-key', 'test-api-key')
            && ($body['outputDimensionality'] ?? null) === 3;
    });
});

test('embedTexts returns vectors in input order with configured dimensionality', function (): void {
    Http::fake([
        '*:batchEmbedContents*' => Http::response([
            'embeddings' => [
                ['values' => [0.1, 0.2, 0.3]],
                ['values' => [0.4, 0.5, 0.6]],
            ],
        ], 200),
    ]);

    $result = geminiClient()->embedTexts(['first text', 'second text']);

    expect($result->vectors)->toBe([
        [0.1, 0.2, 0.3],
        [0.4, 0.5, 0.6],
    ])
        ->and($result->dimensions)->toBe(3)
        ->and($result->model)->toBe(config('ai.gemini.embedding_model'));

    Http::assertSent(function ($request): bool {
        $body = $request->data();
        $requests = $body['requests'] ?? [];

        return str_contains($request->url(), ':batchEmbedContents')
            && count($requests) === 2
            && ($requests[0]['outputDimensionality'] ?? null) === 3
            && ($requests[1]['content']['parts'][0]['text'] ?? null) === 'second text';
    });
});

test('embedTexts returns empty result for empty input without calling api', function (): void {
    Http::fake();

    $result = geminiClient()->embedTexts([]);

    expect($result->vectors)->toBe([])
        ->and($result->latencyMs)->toBe(0);

    Http::assertNothingSent();
});

test('embedTexts throws rate limit exception on http 429', function (): void {
    Http::fake([
        '*:batchEmbedContents*' => Http::response(['error' => 'rate limit'], 429),
    ]);

    try {
        geminiClient()->embedTexts(['text one']);
        expect(false)->toBeTrue('Expected GeminiClientException');
    } catch (GeminiClientException $e) {
        expect($e->errorCode)->toBe(GeminiClientException::RATE_LIMIT)
            ->and($e->httpStatus)->toBe(429);
    }
});

test('generateAnswer sends api key via header not query string', function (): void {
    Http::fake([
        '*:generateContent*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => 'ok'],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    geminiClient()->generateAnswer(new GeminiGenerateContentRequest(userText: 'Xin chao'));

    Http::assertSent(function ($request): bool {
        return str_contains($request->url(), ':generateContent')
            && ! str_contains($request->url(), 'key=')
            && $request->hasHeader('x-goog-api-key', 'test-api-key');
    });
});

test('embedText throws when api key is missing', function (): void {
    config(['ai.gemini.api_key' => '']);

    geminiClient()->embedText('test');
})->throws(GeminiClientException::class);

test('generateAnswer parses text and token usage', function (): void {
    Http::fake([
        '*:generateContent*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => '  Xin chao, toi co the giup gi?  '],
                        ],
                    ],
                ],
            ],
            'usageMetadata' => [
                'promptTokenCount' => 11,
                'candidatesTokenCount' => 7,
                'totalTokenCount' => 18,
            ],
        ], 200),
    ]);

    $result = geminiClient()->generateAnswer(new GeminiGenerateContentRequest(
        userText: 'Xin chao',
        systemInstruction: 'Ban la tro ly Bookify.',
    ));

    expect($result->text)->toBe('Xin chao, toi co the giup gi?')
        ->and($result->tokenUsage)->toBe([
            'prompt' => 11,
            'candidates' => 7,
            'total' => 18,
        ]);
});

test('generateAnswer throws on empty candidates', function (): void {
    Http::fake([
        '*:generateContent*' => Http::response([
            'candidates' => [],
        ], 200),
    ]);

    geminiClient()->generateAnswer(new GeminiGenerateContentRequest(userText: 'Xin chao'));
})->throws(GeminiClientException::class);

test('generateAnswer retries on server error then succeeds', function (): void {
    Http::fake([
        '*:generateContent*' => Http::sequence()
            ->push('', 503)
            ->push([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Tra loi sau retry'],
                            ],
                        ],
                    ],
                ],
            ], 200),
    ]);

    $result = geminiClient()->generateAnswer(new GeminiGenerateContentRequest(userText: 'Xin chao'));

    expect($result->text)->toBe('Tra loi sau retry');
    Http::assertSentCount(2);
});

test('generateAnswer does not retry on client error', function (): void {
    Http::fake([
        '*:generateContent*' => Http::response(['error' => 'bad request'], 400),
    ]);

    try {
        geminiClient()->generateAnswer(new GeminiGenerateContentRequest(userText: 'Xin chao'));
        expect(false)->toBeTrue('Expected GeminiClientException');
    } catch (GeminiClientException $e) {
        expect($e->errorCode)->toBe(GeminiClientException::API_ERROR)
            ->and($e->httpStatus)->toBe(400);
    }

    Http::assertSentCount(1);
});

test('generateAnswer throws timeout after connection failures', function (): void {
    Http::fake(function () {
        throw new ConnectionException('Connection timed out');
    });

    geminiClient()->generateAnswer(new GeminiGenerateContentRequest(userText: 'Xin chao'));
})->throws(GeminiClientException::class);
