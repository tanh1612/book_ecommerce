<?php

use App\Enums\Ai\ChatFeedbackRating;
use App\Models\Account;
use App\Models\AiChatMessage;
use App\Services\Ai\ChatFeedbackService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

test('canFeedback allows matching session_id for guest message', function (): void {
    $message = AiChatMessage::query()->create([
        'session_id' => '550e8400-e29b-41d4-a716-446655440000',
        'account_id' => null,
        'question' => 'Q',
        'answer' => 'A',
        'model_version' => 'test',
        'retrieval_strategy' => 'none',
        'retrieval_matched' => false,
    ]);

    expect(app(ChatFeedbackService::class)->canFeedback($message, '550e8400-e29b-41d4-a716-446655440000', null))
        ->toBeTrue();
});

test('canFeedback allows matching account_id for member message', function (): void {
    $account = Account::factory()->create();

    $message = AiChatMessage::query()->create([
        'session_id' => '550e8400-e29b-41d4-a716-446655440000',
        'account_id' => $account->id,
        'question' => 'Q',
        'answer' => 'A',
        'model_version' => 'test',
        'retrieval_strategy' => 'none',
        'retrieval_matched' => false,
    ]);

    expect(app(ChatFeedbackService::class)->canFeedback($message, null, $account->id))->toBeTrue();
});

test('canFeedback rejects session_id alone for member owned message', function (): void {
    $account = Account::factory()->create();

    $message = AiChatMessage::query()->create([
        'session_id' => '550e8400-e29b-41d4-a716-446655440000',
        'account_id' => $account->id,
        'question' => 'Q',
        'answer' => 'A',
        'model_version' => 'test',
        'retrieval_strategy' => 'none',
        'retrieval_matched' => false,
    ]);

    expect(app(ChatFeedbackService::class)->canFeedback(
        $message,
        '550e8400-e29b-41d4-a716-446655440000',
        $account->id + 1,
    ))->toBeFalse();
});

test('upsert throws when caller is not authorized', function (): void {
    $message = AiChatMessage::query()->create([
        'session_id' => '550e8400-e29b-41d4-a716-446655440000',
        'account_id' => null,
        'question' => 'Q',
        'answer' => 'A',
        'model_version' => 'test',
        'retrieval_strategy' => 'none',
        'retrieval_matched' => false,
    ]);

    app(ChatFeedbackService::class)->upsert(
        message: $message,
        rating: ChatFeedbackRating::Up,
        sessionId: '660e8400-e29b-41d4-a716-446655440099',
        authenticatedAccountId: null,
    );
})->throws(AuthorizationException::class);
