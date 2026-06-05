<?php

use App\Enums\Ai\ChatIntent;
use App\Services\Ai\ChatIntentRouter;
use App\Services\Ai\Contracts\ChatIntentClassifier;
use App\Services\Ai\Dto\ChatIntentClassificationResult;
use App\Services\Ai\Dto\ChatIntentRouteResult;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    config([
        'ai.intent.classifier_enabled' => true,
        'ai.intent.classifier_confidence_threshold' => 0.80,
        'ai.intent.classifier_max_question_length' => 240,
    ]);
});

function routerWithClassifier(ChatIntentClassifier $classifier): ChatIntentRouter
{
    app()->instance(ChatIntentClassifier::class, $classifier);

    return app(ChatIntentRouter::class);
}

test('rule matched small talk does not call classifier', function (): void {
    $classifier = Mockery::mock(ChatIntentClassifier::class);
    $classifier->shouldNotReceive('classify');

    $result = routerWithClassifier($classifier)->route('chao ban');

    expect($result->shouldShortCircuit)->toBeTrue()
        ->and($result->intent)->toBe(ChatIntent::SmallTalkGreeting)
        ->and($result->strategy)->toBe('rule');
});

test('rule matched book intent does not call classifier', function (): void {
    $classifier = Mockery::mock(ChatIntentClassifier::class);
    $classifier->shouldNotReceive('classify');

    $result = routerWithClassifier($classifier)->route('ban co sach nao hay khong');

    expect($result->shouldShortCircuit)->toBeFalse()
        ->and($result->intent)->toBe(ChatIntent::BookSearch)
        ->and($result->strategy)->toBe('rule');
});

test('unknown with classifier disabled stays unknown', function (): void {
    config(['ai.intent.classifier_enabled' => false]);

    $classifier = Mockery::mock(ChatIntentClassifier::class);
    $classifier->shouldNotReceive('classify');

    $result = routerWithClassifier($classifier)->route('toi dang phan van');

    expect($result->intent)->toBe(ChatIntent::Unknown)
        ->and($result->shouldShortCircuit)->toBeFalse()
        ->and($result->strategy)->toBe('fallback');
});

test('unknown with classifier small talk high confidence short-circuits', function (): void {
    $classifier = Mockery::mock(ChatIntentClassifier::class);
    $classifier->shouldReceive('classify')
        ->once()
        ->andReturn(new ChatIntentClassificationResult(
            intent: ChatIntent::SmallTalkStatusCheck,
            confidence: 0.93,
            strategy: 'llm',
        ));

    $result = routerWithClassifier($classifier)->route('alo ban oi nghe duoc khong');

    expect($result->shouldShortCircuit)->toBeTrue()
        ->and($result->intent)->toBe(ChatIntent::SmallTalkStatusCheck)
        ->and($result->strategy)->toBe('llm')
        ->and(str_contains((string) $result->response, 'Có, mình nghe thấy bạn'))->toBeTrue();
});

test('unknown with classifier unsupported high confidence short-circuits', function (): void {
    $classifier = Mockery::mock(ChatIntentClassifier::class);
    $classifier->shouldReceive('classify')
        ->once()
        ->andReturn(new ChatIntentClassificationResult(
            intent: ChatIntent::UnsupportedOrder,
            confidence: 0.9,
            strategy: 'llm',
        ));

    $result = routerWithClassifier($classifier)->route('minh can xem tinh trang giao hang');

    expect($result->shouldShortCircuit)->toBeTrue()
        ->and($result->intent)->toBe(ChatIntent::UnsupportedOrder);
});

test('unknown with classifier book high confidence continues pipeline', function (): void {
    $classifier = Mockery::mock(ChatIntentClassifier::class);
    $classifier->shouldReceive('classify')
        ->once()
        ->andReturn(new ChatIntentClassificationResult(
            intent: ChatIntent::BookRecommendation,
            confidence: 0.87,
            strategy: 'llm',
        ));

    $result = routerWithClassifier($classifier)->route('co truyen nao nhe nhang cuoi tuan khong');

    expect($result->shouldShortCircuit)->toBeFalse()
        ->and($result->intent)->toBe(ChatIntent::BookRecommendation)
        ->and($result->strategy)->toBe('llm');
});

test('unknown with classifier low confidence continues pipeline', function (): void {
    $classifier = Mockery::mock(ChatIntentClassifier::class);
    $classifier->shouldReceive('classify')
        ->once()
        ->andReturn(new ChatIntentClassificationResult(
            intent: ChatIntent::SmallTalkGreeting,
            confidence: 0.55,
            strategy: 'llm',
        ));

    $result = routerWithClassifier($classifier)->route('toi dang phan van');

    expect($result->shouldShortCircuit)->toBeFalse()
        ->and($result->intent)->toBe(ChatIntent::Unknown)
        ->and($result->strategy)->toBe('fallback');
});

test('question longer than classifier max length skips classifier', function (): void {
    config(['ai.intent.classifier_max_question_length' => 20]);

    $classifier = Mockery::mock(ChatIntentClassifier::class);
    $classifier->shouldNotReceive('classify');

    $result = routerWithClassifier($classifier)->route(str_repeat('a', 30));

    expect($result->intent)->toBe(ChatIntent::Unknown)
        ->and($result->shouldShortCircuit)->toBeFalse();
});
