<?php

use App\Models\Account;
use App\Models\Book;
use App\Models\Inventory;
use App\Services\Ai\BookRagRetriever;
use App\Services\Ai\ChatHistoryStore;
use App\Services\Ai\Dto\BookRagRetrievalResult;
use App\Services\Ai\Dto\BookRagRetrievedDocument;
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

test('contract guest chat success returns full data and meta envelope', function (): void {
    $sessionId = '550e8400-e29b-41d4-a716-446655440010';
    $answer = 'Ban co the tham khao sach ve ky nang giao tiep.';

    fakeGeminiChatSuccess($answer);

    $response = postChat(['session_id' => $sessionId]);

    assertChatSuccessContract($response, $sessionId, expectEvaluation: true);

    expect($response->json('data.message_id'))->toBeInt()->toBeGreaterThan(0)
        ->and($response->json('data.sources'))->toBe([]);
});

test('contract guest chat then feedback with matching session_id', function (): void {
    $sessionId = '550e8400-e29b-41d4-a716-446655440011';

    fakeGeminiChatSuccess();

    $chatResponse = postChat(['session_id' => $sessionId]);
    assertChatSuccessContract($chatResponse, $sessionId);

    $messageId = (int) $chatResponse->json('data.message_id');

    $feedbackResponse = postFeedback($messageId, [
        'session_id' => $sessionId,
        'rating' => 'up',
    ]);

    assertFeedbackSavedContract($feedbackResponse);
});

test('contract member chat then feedback without session_id', function (): void {
    $account = Account::factory()->create();
    $sessionId = '550e8400-e29b-41d4-a716-446655440012';

    fakeGeminiChatSuccess();

    $chatResponse = test()->actingAs($account, 'web')
        ->postJson('/api/v1/ai/chat', validChatPayload(['session_id' => $sessionId]));

    assertChatSuccessContract($chatResponse, $sessionId);

    $messageId = (int) $chatResponse->json('data.message_id');

    $feedbackResponse = test()->actingAs($account, 'web')
        ->postJson("/api/v1/ai/messages/{$messageId}/feedback", [
            'rating' => 'down',
        ]);

    assertFeedbackSavedContract($feedbackResponse);
});

test('contract gemini failure returns fallback meta without evaluation and no redis history', function (): void {
    config(['ai.gemini.retry_times' => 0]);

    Http::fake([
        '*:generateContent*' => function () {
            throw new Illuminate\Http\Client\ConnectionException('Connection timed out');
        },
    ]);

    $sessionId = '550e8400-e29b-41d4-a716-446655440013';

    $response = postChat(['session_id' => $sessionId]);

    assertChatFallbackContract($response, $sessionId, 'gemini_chat_failed');

    expect(app(ChatHistoryStore::class)->getAll($sessionId))->toBe([]);
});

test('contract db log failure returns null message_id null evaluation but keeps redis history', function (): void {
    $sessionId = '550e8400-e29b-41d4-a716-446655440014';
    $answer = 'Tra loi khi db loi nhung gemini ok.';

    Schema::dropIfExists('ai_chat_feedback');
    Schema::dropIfExists('ai_chat_evaluations');
    Schema::dropIfExists('ai_chat_messages');
    fakeGeminiChatSuccess($answer);

    $response = postChat([
        'session_id' => $sessionId,
        'question' => 'Cau hoi khi db loi',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.message_id', null)
        ->assertJsonPath('data.answer', $answer)
        ->assertJsonPath('meta.evaluation', null)
        ->assertJsonStructure([
            'data' => ['message_id', 'answer', 'sources'],
            'meta' => [
                'session_id',
                'model',
                'retrieval' => ['strategy', 'top_score', 'matched'],
            ],
        ]);

    expect(app(ChatHistoryStore::class)->getAll($sessionId))->toHaveCount(2);
});

test('contract high match response includes sources item shape', function (): void {
    $book = Book::factory()->create([
        'name' => 'Dac Nhan Tam',
        'slug' => 'dac-nhan-tam',
        'selling_price' => 86000,
    ]);
    Inventory::factory()->create([
        'book_id' => $book->id,
        'quantity' => 8,
        'reserved_quantity' => 0,
    ]);

    $mock = Mockery::mock(BookRagRetriever::class);
    $mock->shouldReceive('retrieve')->andReturn(new BookRagRetrievalResult(
        matched: true,
        topScore: 0.82,
        documents: [
            new BookRagRetrievedDocument((int) $book->id, 0.82, 'Dac Nhan Tam', 'dac-nhan-tam', []),
        ],
        strategy: 'hybrid',
    ));
    app()->instance(BookRagRetriever::class, $mock);

    fakeGeminiChatSuccess('Dac Nhan Tam co gia 86.000 VND.');

    $sessionId = '550e8400-e29b-41d4-a716-446655440015';

    $response = postChat([
        'session_id' => $sessionId,
        'question' => 'Dac Nhan Tam gia bao nhieu?',
    ]);

    assertChatSuccessContract($response, $sessionId);

    $response->assertJsonStructure([
        'data' => [
            'sources' => [
                ['book_id', 'name', 'slug', 'score'],
            ],
        ],
    ])
        ->assertJsonPath('data.sources.0.book_id', $book->id)
        ->assertJsonPath('data.sources.0.slug', 'dac-nhan-tam')
        ->assertJsonPath('meta.retrieval.matched', true);
});
