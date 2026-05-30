<?php

use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
});

function validChatPayload(array $overrides = []): array
{
    return array_merge([
        'session_id' => '550e8400-e29b-41d4-a716-446655440000',
        'question' => 'Toi muon tim sach ve ky nang giao tiep',
    ], $overrides);
}

function postChat(array $payload = []): \Illuminate\Testing\TestResponse
{
    return test()->postJson('/api/v1/ai/chat', validChatPayload($payload));
}

test('guest chat returns stub response with expected contract', function (): void {
    $sessionId = '550e8400-e29b-41d4-a716-446655440000';

    postChat(['session_id' => $sessionId])
        ->assertOk()
        ->assertJsonPath('data.message_id', null)
        ->assertJsonPath('data.answer', config('ai.chat.stub_message'))
        ->assertJsonPath('data.sources', [])
        ->assertJsonPath('meta.session_id', $sessionId)
        ->assertJsonPath('meta.model', config('ai.gemini.chat_model'))
        ->assertJsonPath('meta.retrieval.strategy', 'none')
        ->assertJsonPath('meta.retrieval.top_score', null)
        ->assertJsonPath('meta.retrieval.matched', false)
        ->assertJsonPath('meta.evaluation', null);
});

test('authenticated member can chat and receives stub response', function (): void {
    $account = Account::factory()->create();

    $this->actingAs($account)
        ->postJson('/api/v1/ai/chat', validChatPayload())
        ->assertOk()
        ->assertJsonPath('data.message_id', null)
        ->assertJsonPath('meta.retrieval.strategy', 'none');
});

test('chat requires session_id', function (): void {
    $this->postJson('/api/v1/ai/chat', [
        'question' => 'Tim sach hay',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('session_id');
});

test('chat rejects invalid session_id format', function (): void {
    postChat(['session_id' => 'not-a-uuid'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('session_id');
});

test('chat requires question', function (): void {
    $this->postJson('/api/v1/ai/chat', [
        'session_id' => '550e8400-e29b-41d4-a716-446655440000',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('question');
});

test('chat rejects question shorter than minimum length', function (): void {
    postChat(['question' => 'a'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('question');
});

test('chat rejects question longer than maximum length', function (): void {
    postChat(['question' => str_repeat('a', config('ai.chat.max_question_length') + 1)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('question');
});

test('guest chat is throttled per ip and session_id', function (): void {
    config(['ai.rate_limits.guest_per_minute' => 3]);

    $sessionId = '550e8400-e29b-41d4-a716-446655440000';

    for ($i = 0; $i < 3; $i++) {
        postChat(['session_id' => $sessionId])->assertOk();
    }

    postChat(['session_id' => $sessionId])
        ->assertStatus(429)
        ->assertJsonPath('message', 'Ban dang gui qua nhieu tin nhan, vui long thu lai sau.');
});

test('member chat is throttled per account_id', function (): void {
    config(['ai.rate_limits.member_per_minute' => 3]);

    $account = Account::factory()->create();

    for ($i = 0; $i < 3; $i++) {
        $this->actingAs($account)
            ->postJson('/api/v1/ai/chat', validChatPayload())
            ->assertOk();
    }

    $this->actingAs($account)
        ->postJson('/api/v1/ai/chat', validChatPayload())
        ->assertStatus(429)
        ->assertJsonPath('message', 'Ban dang gui qua nhieu tin nhan, vui long thu lai sau.');
});
