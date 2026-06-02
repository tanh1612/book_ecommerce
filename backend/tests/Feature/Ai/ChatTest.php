<?php

use App\Models\Account;
use App\Models\AiChatMessage;
use App\Services\Ai\ChatHistoryStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['ai.gemini.api_key' => 'test-api-key']);
    Cache::store((string) config('ai.chat.history_store'))->flush();
    Http::preventStrayRequests();
    bindDefaultBookRagRetriever();
});

test('guest chat returns gemini answer with expected contract and logs message', function (): void {
    $sessionId = '550e8400-e29b-41d4-a716-446655440000';
    $answer = 'Ban co the tham khao sach ve ky nang giao tiep.';

    fakeGeminiChatSuccess($answer);


    $response = postChat(['session_id' => $sessionId]);

    $response
        ->assertOk()
        ->assertJsonPath('data.message_id', fn ($id) => is_int($id) && $id > 0)
        ->assertJsonPath('data.answer', $answer)
        ->assertJsonPath('data.sources', [])
        ->assertJsonPath('meta.session_id', $sessionId)
        ->assertJsonPath('meta.model', config('ai.gemini.chat_model'))
        ->assertJsonPath('meta.retrieval.strategy', 'none')
        ->assertJsonPath('meta.retrieval.top_score', null)
        ->assertJsonPath('meta.retrieval.matched', false)
        ->assertJsonPath('meta.evaluation.verdict', fn ($verdict) => in_array($verdict, ['pass', 'warning', 'fail'], true))
        ->assertJsonPath('meta.evaluation.groundedness_score', fn ($score) => is_numeric($score))
        ->assertJsonPath('meta.evaluation.relevance_score', fn ($score) => is_numeric($score))
        ->assertJsonPath('meta.evaluation.has_hallucination_risk', fn ($risk) => is_bool($risk));

    $messageId = (int) $response->json('data.message_id');

    $this->assertDatabaseHas('ai_chat_messages', [
        'id' => $messageId,
        'session_id' => $sessionId,
        'account_id' => null,
        'question' => 'Toi muon tim sach ve ky nang giao tiep',
        'answer' => $answer,
        'model_version' => config('ai.gemini.chat_model'),
        'retrieval_strategy' => 'none',
        'retrieval_matched' => false,
        'error_code' => null,
    ]);

    $this->assertDatabaseHas('ai_chat_evaluations', [
        'message_id' => $messageId,
        'verdict' => $response->json('meta.evaluation.verdict'),
    ]);

    expect(app(ChatHistoryStore::class)->getAll($sessionId))->toHaveCount(2);
});

test('authenticated member chat logs account_id and persists redis history', function (): void {
    fakeGeminiChatSuccess();

    $account = Account::factory()->create();
    $sessionId = '550e8400-e29b-41d4-a716-446655440000';

    $this->actingAs($account, 'web')
        ->postJson('/api/v1/ai/chat', validChatPayload(['session_id' => $sessionId]))
        ->assertOk()
        ->assertJsonPath('meta.retrieval.strategy', 'none');

    $this->assertDatabaseHas('ai_chat_messages', [
        'session_id' => $sessionId,
        'account_id' => $account->id,
    ]);

    $history = app(ChatHistoryStore::class)->getAll($sessionId);

    expect($history)->toHaveCount(2)
        ->and($history[0]['role'])->toBe('user')
        ->and($history[1]['role'])->toBe('assistant');
});

test('gemini failure returns fallback without appending redis history', function (): void {
    config(['ai.gemini.retry_times' => 0]);

    Http::fake([
        '*:generateContent*' => function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
        },
    ]);

    $sessionId = '550e8400-e29b-41d4-a716-446655440000';

    postChat(['session_id' => $sessionId])
        ->assertOk()
        ->assertJsonPath('data.answer', config('ai.chat.fallback_message'))
        ->assertJsonPath('meta.model', null)
        ->assertJsonPath('meta.error_code', 'gemini_chat_failed')
        ->assertJsonPath('meta.evaluation', null);

    expect(app(ChatHistoryStore::class)->getAll($sessionId))->toBe([]);

    $this->assertDatabaseHas('ai_chat_messages', [
        'session_id' => $sessionId,
        'answer' => config('ai.chat.fallback_message'),
        'error_code' => 'gemini_chat_failed',
    ]);
});

test('missing gemini api key returns fallback response', function (): void {
    config(['ai.gemini.api_key' => '']);

    $sessionId = '550e8400-e29b-41d4-a716-446655440000';

    postChat(['session_id' => $sessionId])
        ->assertOk()
        ->assertJsonPath('data.answer', config('ai.chat.fallback_message'))
        ->assertJsonPath('meta.error_code', 'gemini_chat_failed')
        ->assertJsonPath('meta.model', null);

    Http::assertNothingSent();
});

test('redis history is appended when gemini succeeds even if db log fails', function (): void {
    $sessionId = '550e8400-e29b-41d4-a716-446655440000';
    $answer = 'Tra loi khi db loi nhung gemini ok.';

    Schema::dropIfExists('ai_chat_feedback');
    Schema::dropIfExists('ai_chat_evaluations');
    Schema::dropIfExists('ai_chat_messages');
    fakeGeminiChatSuccess($answer);

    postChat([
        'session_id' => $sessionId,
        'question' => 'Cau hoi khi db loi',
    ])
        ->assertOk()
        ->assertJsonPath('data.message_id', null)
        ->assertJsonPath('data.answer', $answer)
        ->assertJsonPath('meta.evaluation', null);

    $history = app(ChatHistoryStore::class)->getAll($sessionId);

    expect($history)->toHaveCount(2)
        ->and($history[0]['content'])->toBe('Cau hoi khi db loi')
        ->and($history[1]['content'])->toBe($answer);
});

test('multiple chats with same session_id append redis history', function (): void {
    fakeGeminiChatSuccess();

    $sessionId = '550e8400-e29b-41d4-a716-446655440000';

    postChat([
        'session_id' => $sessionId,
        'question' => 'Cau hoi 1',
    ])->assertOk();

    postChat([
        'session_id' => $sessionId,
        'question' => 'Cau hoi 2',
    ])->assertOk();

    expect(app(ChatHistoryStore::class)->getAll($sessionId))->toHaveCount(4);
    expect(AiChatMessage::query()->where('session_id', $sessionId)->count())->toBe(2);
});

test('out of scope intents short-circuit without rag or gemini calls', function (string $question): void {
    $sessionId = '550e8400-e29b-41d4-a716-446655440000';

    postChat([
        'session_id' => $sessionId,
        'question' => $question,
    ])
        ->assertOk()
        ->assertJsonPath('data.sources', [])
        ->assertJsonPath('meta.model', null)
        ->assertJsonPath('meta.retrieval.strategy', 'none')
        ->assertJsonPath('meta.retrieval.matched', false)
        ->assertJsonPath('meta.evaluation', null);

    Http::assertNothingSent();

    expect(app(ChatHistoryStore::class)->getAll($sessionId))->toBe([]);

    $this->assertDatabaseHas('ai_chat_messages', [
        'session_id' => $sessionId,
        'question' => $question,
        'retrieval_strategy' => 'none',
        'retrieval_matched' => false,
        'retrieved_books' => null,
        'error_code' => null,
    ]);
})->with([
    'order status' => 'Don hang cua toi dau roi?',
    'cancel order' => 'Toi muon huy don hang',
    'payment issue' => 'Thanh toan VNPAY bi loi',
    'refund request' => 'Toi muon refund/hoan tien',
    'change password' => 'Doi mat khau tai khoan the nao?',
    'private address' => 'Dia chi cua toi la gi?',
    'non book product' => 'Bookify co ban dien thoai khong?',
]);

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
    fakeGeminiChatSuccess();

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
    fakeGeminiChatSuccess();

    config(['ai.rate_limits.member_per_minute' => 3]);

    $account = Account::factory()->create();

    for ($i = 0; $i < 3; $i++) {
        $this->actingAs($account, 'web')
            ->postJson('/api/v1/ai/chat', validChatPayload())
            ->assertOk();
    }

    $this->actingAs($account, 'web')
        ->postJson('/api/v1/ai/chat', validChatPayload())
        ->assertStatus(429)
        ->assertJsonPath('message', 'Ban dang gui qua nhieu tin nhan, vui long thu lai sau.');
});
