<?php

use App\Enums\Ai\ChatIntent;
use App\Services\Ai\GeminiChatIntentClassifier;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    config([
        'ai.gemini.api_key' => 'test-api-key',
        'ai.intent.classifier_timeout_seconds' => 3,
        'ai.intent.classifier_retry_times' => 0,
        'ai.intent.classifier_cache_ttl_seconds' => 3600,
    ]);

    Cache::flush();
    Http::preventStrayRequests();
});

function intentClassifier(): GeminiChatIntentClassifier
{
    return app(GeminiChatIntentClassifier::class);
}

function fakeIntentClassificationResponse(string $body, int $status = 200): void
{
    Http::fake([
        '*:generateContent*' => function ($request) use ($body, $status) {
            $system = $request->data()['systemInstruction']['parts'][0]['text'] ?? '';

            if (! str_contains($system, 'Classify the user')) {
                return Http::response([], 404);
            }

            return Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => $body],
                            ],
                        ],
                    ],
                ],
            ], $status);
        },
    ]);
}

test('classifier returns parsed small talk intent from valid json', function (): void {
    fakeIntentClassificationResponse('{"intent":"small_talk.greeting","confidence":0.92}');

    $result = intentClassifier()->classify('alo ban oi');

    expect($result->intent)->toBe(ChatIntent::SmallTalkGreeting)
        ->and($result->confidence)->toBe(0.92)
        ->and($result->strategy)->toBe('llm');
});

test('classifier returns parsed unsupported intent from valid json', function (): void {
    fakeIntentClassificationResponse('{"intent":"unsupported.order","confidence":0.88}');

    $result = intentClassifier()->classify('don hang cua toi dau roi');

    expect($result->intent)->toBe(ChatIntent::UnsupportedOrder)
        ->and($result->confidence)->toBe(0.88);
});

test('classifier falls back to unknown for invalid enum', function (): void {
    fakeIntentClassificationResponse('{"intent":"not.real","confidence":0.9}');

    $result = intentClassifier()->classify('mot cau la');

    expect($result->intent)->toBe(ChatIntent::Unknown)
        ->and($result->confidence)->toBe(0.0)
        ->and($result->strategy)->toBe('fallback');
});

test('classifier falls back to unknown for invalid json', function (): void {
    fakeIntentClassificationResponse('not-json');

    $result = intentClassifier()->classify('mot cau la');

    expect($result->intent)->toBe(ChatIntent::Unknown)
        ->and($result->strategy)->toBe('fallback');
});

test('classifier falls back to unknown on timeout', function (): void {
    Http::fake([
        '*:generateContent*' => function () {
            throw new ConnectionException('Connection timed out');
        },
    ]);

    $result = intentClassifier()->classify('mot cau la');

    expect($result->intent)->toBe(ChatIntent::Unknown)
        ->and($result->strategy)->toBe('fallback');
});

test('classifier falls back to unknown when confidence is outside range', function (): void {
    fakeIntentClassificationResponse('{"intent":"small_talk.thanks","confidence":1.5}');

    $result = intentClassifier()->classify('cam on ban');

    expect($result->intent)->toBe(ChatIntent::Unknown)
        ->and($result->strategy)->toBe('fallback');
});

test('classifier uses cache and avoids duplicate external calls', function (): void {
    fakeIntentClassificationResponse('{"intent":"small_talk.thanks","confidence":0.91}');

    $classifier = intentClassifier();
    $question = 'cam on nhieu nhe';

    $classifier->classify($question);
    $classifier->classify($question);

    Http::assertSentCount(1);
});
