<?php

use App\Exceptions\Ai\GeminiClientException;
use App\Models\AiChatMessage;
use App\Models\Book;
use App\Models\Inventory;
use App\Services\Ai\BookRagRetriever;
use App\Services\Ai\ChatHistoryStore;
use App\Services\Ai\Dto\BookRagRetrievalResult;
use App\Services\Ai\Dto\BookRagRetrievedDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['ai.gemini.api_key' => 'test-api-key']);
    Cache::store((string) config('ai.chat.history_store'))->flush();
    Http::preventStrayRequests();
    bindBookRagRetriever(defaultUnmatchedRetrieval());
});

function bindBookRagRetriever(BookRagRetrievalResult $result): void
{
    $mock = Mockery::mock(BookRagRetriever::class);
    $mock->shouldReceive('retrieve')->andReturn($result);
    app()->instance(BookRagRetriever::class, $mock);
}

function bindBookRagRetrieverThrowable(Throwable $throwable): void
{
    $mock = Mockery::mock(BookRagRetriever::class);
    $mock->shouldReceive('retrieve')->andThrow($throwable);
    app()->instance(BookRagRetriever::class, $mock);
}

function defaultUnmatchedRetrieval(): BookRagRetrievalResult
{
    return new BookRagRetrievalResult(
        matched: false,
        topScore: null,
        documents: [],
        strategy: 'none',
    );
}

function matchedHybridRetrieval(int $bookId = 10): BookRagRetrievalResult
{
    return new BookRagRetrievalResult(
        matched: true,
        topScore: 0.82,
        documents: [
            new BookRagRetrievedDocument($bookId, 0.82, 'Dac Nhan Tam', 'dac-nhan-tam', []),
        ],
        strategy: 'hybrid',
    );
}

function ragFakeGeminiChatSuccess(string $answer = 'Ban co the tham khao Dac Nhan Tam voi gia 86.000 VND.'): void
{
    Http::fake([
        '*:generateContent*' => Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => $answer],
                        ],
                    ],
                ],
            ],
            'usageMetadata' => [
                'promptTokenCount' => 120,
                'candidatesTokenCount' => 40,
                'totalTokenCount' => 160,
            ],
        ], 200),
    ]);
}

test('high match chat injects mysql context logs retrieval metadata and returns sources', function (): void {
    $book = Book::factory()->create([
        'name' => 'Dac Nhan Tam',
        'slug' => 'dac-nhan-tam',
        'selling_price' => 86000,
        'average_rating' => 4.5,
        'review_count' => 120,
    ]);
    Inventory::factory()->create([
        'book_id' => $book->id,
        'quantity' => 8,
        'reserved_quantity' => 0,
    ]);

    bindBookRagRetriever(matchedHybridRetrieval((int) $book->id));
    ragFakeGeminiChatSuccess();

    $sessionId = '550e8400-e29b-41d4-a716-446655440000';

    $response = test()->postJson('/api/v1/ai/chat', [
        'session_id' => $sessionId,
        'question' => 'Toi muon sach ve ky nang giao tiep',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.sources.0.book_id', $book->id)
        ->assertJsonPath('data.sources.0.slug', 'dac-nhan-tam')
        ->assertJsonPath('meta.retrieval.strategy', 'hybrid')
        ->assertJsonPath('meta.retrieval.matched', true)
        ->assertJsonPath('meta.retrieval.top_score', 0.82)
        ->assertJsonPath('meta.evaluation.verdict', 'pass')
        ->assertJsonPath('meta.evaluation.has_hallucination_risk', false);

    $messageId = (int) $response->json('data.message_id');

    $this->assertDatabaseHas('ai_chat_messages', [
        'id' => $messageId,
        'session_id' => $sessionId,
        'retrieval_strategy' => 'hybrid',
        'retrieval_matched' => true,
        'error_code' => null,
    ]);

    $message = AiChatMessage::query()->findOrFail($messageId);

    expect($message->token_usage)->toMatchArray([
        'prompt' => 120,
        'candidates' => 40,
        'total' => 160,
    ])
        ->and($message->retrieved_books)->toMatchArray([
            ['book_id' => $book->id, 'score' => 0.82],
        ])
        ->and($message->evaluation)->not->toBeNull()
        ->and($message->evaluation->verdict)->toBe('pass');

    Http::assertSent(function ($request): bool {
        $body = $request->data();

        return str_contains($request->url(), ':generateContent')
            && str_contains((string) ($body['contents'][0]['parts'][0]['text'] ?? ''), 'Giá bán: 86.000 VND')
            && str_contains((string) ($body['contents'][0]['parts'][0]['text'] ?? ''), 'no_relevant_context=false');
    });

    expect(app(ChatHistoryStore::class)->getAll($sessionId))->toHaveCount(2);
});

test('low score rag documents without match keep no context path and empty sources', function (): void {
    $book = Book::factory()->create([
        'name' => 'Sach khac',
        'slug' => 'sach-khac',
        'selling_price' => 50000,
    ]);

    Inventory::factory()->create([
        'book_id' => $book->id,
        'quantity' => 5,
        'reserved_quantity' => 0,
    ]);

    bindBookRagRetriever(new BookRagRetrievalResult(
        matched: false,
        topScore: 0.40,
        documents: [
            new BookRagRetrievedDocument((int) $book->id, 0.40, (string) $book->name, (string) $book->slug, []),
        ],
        strategy: 'hybrid',
    ));

    ragFakeGeminiChatSuccess('Tôi chưa tìm thấy thông tin phù hợp trong dữ liệu hiện có của Bookify.');

    $sessionId = '550e8400-e29b-41d4-a716-446655440040';

    $response = test()->postJson('/api/v1/ai/chat', [
        'session_id' => $sessionId,
        'question' => 'Toi muon sach ve chu de xyzkhongco',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.sources', [])
        ->assertJsonPath('meta.retrieval.matched', false)
        ->assertJsonPath('meta.retrieval.strategy', 'hybrid');

    Http::assertSent(function ($request) use ($book): bool {
        $body = (string) ($request->data()['contents'][0]['parts'][0]['text'] ?? '');

        return str_contains($body, 'no_relevant_context=true')
            && ! str_contains($body, 'Giá bán:')
            && ! str_contains($body, (string) $book->name);
    });

    $this->assertDatabaseHas('ai_chat_messages', [
        'session_id' => $sessionId,
        'retrieval_matched' => false,
        'retrieval_strategy' => 'hybrid',
    ]);
});

test('matched retrieval does not log unrelated book id when answer cites no retrieved source', function (): void {
    $book = Book::factory()->create([
        'name' => 'Dac Nhan Tam',
        'slug' => 'dac-nhan-tam',
        'selling_price' => 86000,
    ]);

    Inventory::factory()->create([
        'book_id' => $book->id,
        'quantity' => 5,
        'reserved_quantity' => 0,
    ]);

    bindBookRagRetriever(matchedHybridRetrieval((int) $book->id));
    ragFakeGeminiChatSuccess('Giao Tiep Thong Minh còn hàng.');

    $sessionId = '550e8400-e29b-41d4-a716-446655440041';

    $response = test()->postJson('/api/v1/ai/chat', [
        'session_id' => $sessionId,
        'question' => 'Toi muon sach ve thanh cong',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.sources', []);

    $message = AiChatMessage::query()->where('session_id', $sessionId)->firstOrFail();

    expect($message->retrieval_matched)->toBeTrue()
        ->and($message->retrieved_books)->toBeNull();
});

test('low match chat uses no context wording and returns empty sources', function (): void {
    bindBookRagRetriever(new BookRagRetrievalResult(
        matched: false,
        topScore: 0.40,
        documents: [],
        strategy: 'hybrid',
    ));

    ragFakeGeminiChatSuccess('Tôi chưa tìm thấy thông tin phù hợp trong dữ liệu hiện có của Bookify.');

    $sessionId = '550e8400-e29b-41d4-a716-446655440000';

    $response = test()->postJson('/api/v1/ai/chat', [
        'session_id' => $sessionId,
        'question' => 'Toi muon sach ve chu de xyzkhongco',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.sources', [])
        ->assertJsonPath('meta.retrieval.matched', false)
        ->assertJsonPath('meta.retrieval.strategy', 'hybrid')
        ->assertJsonPath('meta.evaluation.verdict', 'pass');

    Http::assertSent(function ($request): bool {
        $body = json_encode($request->data(), JSON_UNESCAPED_UNICODE);

        return is_string($body)
            && str_contains($body, 'no_relevant_context=true')
            && str_contains($body, 'dữ liệu hiện có');
    });

    $this->assertDatabaseHas('ai_chat_messages', [
        'session_id' => $sessionId,
        'retrieval_matched' => false,
        'retrieval_strategy' => 'hybrid',
    ]);
});

test('matched retrieval without mysql context returns empty sources and effective matched false', function (): void {
    bindBookRagRetriever(matchedHybridRetrieval(99999));
    ragFakeGeminiChatSuccess('Tôi chưa tìm thấy thông tin phù hợp trong dữ liệu hiện có của Bookify.');

    $sessionId = '550e8400-e29b-41d4-a716-446655440000';

    $response = test()->postJson('/api/v1/ai/chat', [
        'session_id' => $sessionId,
        'question' => 'Toi muon sach ve ky nang giao tiep',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.sources', [])
        ->assertJsonPath('meta.retrieval.strategy', 'hybrid')
        ->assertJsonPath('meta.retrieval.top_score', 0.82)
        ->assertJsonPath('meta.retrieval.matched', false);

    Http::assertSent(function ($request): bool {
        $body = $request->data();

        return str_contains((string) ($body['contents'][0]['parts'][0]['text'] ?? ''), 'no_relevant_context=true');
    });

    $this->assertDatabaseHas('ai_chat_messages', [
        'session_id' => $sessionId,
        'retrieval_strategy' => 'hybrid',
        'retrieval_matched' => false,
        'retrieved_books' => null,
    ]);
});

test('gemini failure preserves retrieval metadata and does not append redis history', function (): void {
    config(['ai.gemini.retry_times' => 0]);

    $book = Book::factory()->create([
        'name' => 'Dac Nhan Tam',
        'slug' => 'dac-nhan-tam',
    ]);

    bindBookRagRetriever(matchedHybridRetrieval((int) $book->id));

    Http::fake([
        '*:generateContent*' => function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
        },
    ]);

    $sessionId = '550e8400-e29b-41d4-a716-446655440000';

    test()->postJson('/api/v1/ai/chat', [
        'session_id' => $sessionId,
        'question' => 'Dac Nhan Tam gia bao nhieu?',
    ])
        ->assertOk()
        ->assertJsonPath('data.answer', config('ai.chat.fallback_message'))
        ->assertJsonPath('meta.error_code', 'gemini_chat_failed')
        ->assertJsonPath('meta.retrieval.strategy', 'hybrid')
        ->assertJsonPath('meta.retrieval.matched', false)
        ->assertJsonPath('meta.evaluation', null);

    expect(app(ChatHistoryStore::class)->getAll($sessionId))->toBe([]);

    $this->assertDatabaseHas('ai_chat_messages', [
        'session_id' => $sessionId,
        'retrieval_strategy' => 'hybrid',
        'retrieval_matched' => false,
        'retrieved_books' => null,
        'error_code' => 'gemini_chat_failed',
    ]);
});

test('embedding failure returns fallback and does not append redis history', function (): void {
    bindBookRagRetrieverThrowable(new GeminiClientException(
        message: 'Gemini timeout',
        errorCode: GeminiClientException::TIMEOUT,
        httpStatus: null,
        latencyMs: 15,
    ));

    $sessionId = '550e8400-e29b-41d4-a716-446655440000';

    test()->postJson('/api/v1/ai/chat', [
        'session_id' => $sessionId,
        'question' => 'Tim sach hay',
    ])
        ->assertOk()
        ->assertJsonPath('data.answer', config('ai.chat.fallback_message'))
        ->assertJsonPath('meta.error_code', 'embedding_failed')
        ->assertJsonPath('meta.retrieval.strategy', 'none')
        ->assertJsonPath('meta.retrieval.matched', false)
        ->assertJsonPath('meta.evaluation', null);

    expect(app(ChatHistoryStore::class)->getAll($sessionId))->toBe([]);

    Http::assertNothingSent();

    $this->assertDatabaseHas('ai_chat_messages', [
        'session_id' => $sessionId,
        'error_code' => 'embedding_failed',
        'retrieval_strategy' => 'none',
    ]);
});
