<?php

use App\Models\Account;
use App\Models\AiChatFeedback;
use App\Models\AiChatMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['ai.gemini.api_key' => 'test-api-key']);
    Http::preventStrayRequests();
    bindDefaultBookRagRetriever();
});

function createGuestChatMessage(string $sessionId): AiChatMessage
{
    fakeGeminiChatSuccess();

    postChat([
        'session_id' => $sessionId,
        'question' => 'Tim sach hay',
    ])->assertOk();

    return AiChatMessage::query()->where('session_id', $sessionId)->firstOrFail();
}

test('guest can submit thumbs up with matching session_id', function (): void {
    $sessionId = '550e8400-e29b-41d4-a716-446655440000';
    $message = createGuestChatMessage($sessionId);

    postFeedback($message->id, [
        'session_id' => $sessionId,
        'rating' => 'up',
    ])
        ->assertOk()
        ->assertJsonPath('message', 'Feedback saved.');

    $this->assertDatabaseHas('ai_chat_feedback', [
        'message_id' => $message->id,
        'session_id' => $sessionId,
        'account_id' => null,
        'rating' => 'up',
    ]);
});

test('resubmitting feedback updates rating for same message', function (): void {
    $sessionId = '550e8400-e29b-41d4-a716-446655440001';
    $message = createGuestChatMessage($sessionId);

    postFeedback($message->id, [
        'session_id' => $sessionId,
        'rating' => 'up',
    ])->assertOk();

    postFeedback($message->id, [
        'session_id' => $sessionId,
        'rating' => 'down',
    ])->assertOk();

    expect(AiChatFeedback::query()->where('message_id', $message->id)->count())->toBe(1);

    $this->assertDatabaseHas('ai_chat_feedback', [
        'message_id' => $message->id,
        'rating' => 'down',
    ]);
});

test('guest feedback is rejected when session_id does not match message', function (): void {
    $sessionId = '550e8400-e29b-41d4-a716-446655440002';
    $message = createGuestChatMessage($sessionId);

    postFeedback($message->id, [
        'session_id' => '660e8400-e29b-41d4-a716-446655440099',
        'rating' => 'up',
    ])->assertForbidden();

    expect(AiChatFeedback::query()->where('message_id', $message->id)->exists())->toBeFalse();
});

test('guest feedback requires session_id', function (): void {
    $sessionId = '550e8400-e29b-41d4-a716-446655440003';
    $message = createGuestChatMessage($sessionId);

    postFeedback($message->id, [
        'rating' => 'up',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('session_id');
});

test('member can submit feedback by account_id without session_id', function (): void {
    $account = Account::factory()->create();
    $sessionId = '550e8400-e29b-41d4-a716-446655440004';

    fakeGeminiChatSuccess();

    test()->actingAs($account, 'web')
        ->postJson('/api/v1/ai/chat', [
            'session_id' => $sessionId,
            'question' => 'Tim sach cho member',
        ])
        ->assertOk();

    $message = AiChatMessage::query()->where('account_id', $account->id)->firstOrFail();

    test()->actingAs($account, 'web')
        ->postJson("/api/v1/ai/messages/{$message->id}/feedback", [
            'rating' => 'down',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Feedback saved.');

    $this->assertDatabaseHas('ai_chat_feedback', [
        'message_id' => $message->id,
        'account_id' => $account->id,
        'rating' => 'down',
    ]);
});

test('member cannot submit feedback for another members message', function (): void {
    $owner = Account::factory()->create();
    $other = Account::factory()->create();
    $sessionId = '550e8400-e29b-41d4-a716-446655440005';

    fakeGeminiChatSuccess();

    test()->actingAs($owner, 'web')
        ->postJson('/api/v1/ai/chat', [
            'session_id' => $sessionId,
            'question' => 'Cau hoi cua owner',
        ])
        ->assertOk();

    $message = AiChatMessage::query()->where('account_id', $owner->id)->firstOrFail();

    test()->actingAs($other, 'web')
        ->postJson("/api/v1/ai/messages/{$message->id}/feedback", [
            'session_id' => $sessionId,
            'rating' => 'up',
        ])
        ->assertForbidden();
});

test('logged in user can feedback earlier guest message with matching session_id', function (): void {
    $sessionId = '550e8400-e29b-41d4-a716-446655440006';
    $message = createGuestChatMessage($sessionId);
    $account = Account::factory()->create();

    test()->actingAs($account, 'web')
        ->postJson("/api/v1/ai/messages/{$message->id}/feedback", [
            'session_id' => $sessionId,
            'rating' => 'up',
        ])
        ->assertOk();

    $this->assertDatabaseHas('ai_chat_feedback', [
        'message_id' => $message->id,
        'session_id' => $sessionId,
        'rating' => 'up',
    ]);
});

test('feedback rejects invalid rating', function (): void {
    $sessionId = '550e8400-e29b-41d4-a716-446655440007';
    $message = createGuestChatMessage($sessionId);

    postFeedback($message->id, [
        'session_id' => $sessionId,
        'rating' => 'helpful',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('rating');
});

test('feedback returns not found for missing message', function (): void {
    postFeedback(99999, [
        'session_id' => '550e8400-e29b-41d4-a716-446655440000',
        'rating' => 'up',
    ])->assertNotFound();
});

test('guest feedback is throttled per ip and session_id', function (): void {
    config(['ai.rate_limits.feedback_guest_per_minute' => 2]);

    $sessionId = '550e8400-e29b-41d4-a716-446655440008';
    $message = createGuestChatMessage($sessionId);

    postFeedback($message->id, ['session_id' => $sessionId, 'rating' => 'up'])->assertOk();
    postFeedback($message->id, ['session_id' => $sessionId, 'rating' => 'down'])->assertOk();

    postFeedback($message->id, ['session_id' => $sessionId, 'rating' => 'up'])
        ->assertStatus(429);
});
