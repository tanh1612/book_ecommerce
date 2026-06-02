<?php

use App\Models\AiChatMessage;
use App\Models\Book;
use App\Models\Inventory;
use App\Services\Ai\BookRagRetriever;
use App\Services\Ai\ChatContextStore;
use App\Services\Ai\Dto\BookRagRetrievalResult;
use App\Services\Ai\Dto\BookRagRetrievedDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['ai.gemini.api_key' => 'test-api-key']);
    Http::preventStrayRequests();
});

test('exact title stock question injects mysql book even when rag returns another title', function (): void {
    $target = Book::factory()->create([
        'name' => 'Bắt Tiên Xóm Con Chuột',
        'slug' => 'bat-tien-xom-con-chuot',
        'selling_price' => 75000,
    ]);

    Inventory::factory()->create([
        'book_id' => $target->id,
        'quantity' => 0,
        'reserved_quantity' => 0,
    ]);

    $decoy = Book::factory()->create([
        'name' => 'Chuột Đồng Cổ Tích',
        'slug' => 'chuot-dong-co-tich',
    ]);

    $mock = Mockery::mock(BookRagRetriever::class);
    $mock->shouldReceive('retrieve')->andReturn(new BookRagRetrievalResult(
        matched: true,
        topScore: 0.91,
        documents: [
            new BookRagRetrievedDocument((int) $decoy->id, 0.91, 'Chuột Đồng Cổ Tích', 'chuot-dong-co-tich', []),
        ],
        strategy: 'hybrid',
    ));
    app()->instance(BookRagRetriever::class, $mock);
    app()->forgetInstance(\App\Services\Ai\ChatbotService::class);

    fakeGeminiChatSuccess('Sach Bat Tien Xom Con Chuot hien het hang.');

    $sessionId = '550e8400-e29b-41d4-a716-446655440020';

    $response = postChat([
        'session_id' => $sessionId,
        'question' => 'Sách Bắt Tiên Xóm Con Chuột còn hàng không?',
    ]);

    $response->assertOk();

    $noContextMessage = (string) config('ai.chat.no_context_message');
    expect($response->json('data.answer'))->not->toBe($noContextMessage);

    $message = AiChatMessage::query()->where('session_id', $sessionId)->firstOrFail();

    expect(collect($message->retrieved_books)->pluck('book_id')->all())
        ->toContain((int) $target->id);

    Http::assertSent(function ($request) use ($target): bool {
        if (! str_contains($request->url(), ':generateContent')) {
            return false;
        }

        $payload = json_encode($request->data(), JSON_UNESCAPED_UNICODE);

        return is_string($payload)
            && str_contains($payload, '[Book #'.$target->id.']')
            && str_contains($payload, 'Con hang: khong');
    });
});

test('exact stock answer without book name still stores conversation referent for follow up', function (): void {
    $target = Book::factory()->create([
        'name' => 'Bắt Tiên Xóm Con Chuột',
        'slug' => 'bat-tien-xom-con-chuot',
        'selling_price' => 75000,
    ]);

    Inventory::factory()->create([
        'book_id' => $target->id,
        'quantity' => 5,
        'reserved_quantity' => 0,
    ]);

    $mock = Mockery::mock(BookRagRetriever::class);
    $mock->shouldReceive('retrieve')->andReturn(new BookRagRetrievalResult(
        matched: false,
        topScore: null,
        documents: [],
        strategy: 'none',
    ));
    app()->instance(BookRagRetriever::class, $mock);
    app()->forgetInstance(\App\Services\Ai\ChatbotService::class);

    $sessionId = '550e8400-e29b-41d4-a716-446655440021';

    fakeGeminiChatSuccess('Sach nay hien con hang.');

    postChat([
        'session_id' => $sessionId,
        'question' => 'Sách Bắt Tiên Xóm Con Chuột còn hàng không?',
    ])
        ->assertOk()
        ->assertJsonPath('data.sources', []);

    expect(app(ChatContextStore::class)->getLastSources($sessionId))->toMatchArray([
        [
            'book_id' => (int) $target->id,
            'name' => $target->name,
            'slug' => $target->slug,
        ],
    ]);

    $retriever = Mockery::mock(BookRagRetriever::class);
    $retriever->shouldNotReceive('retrieve');
    app()->instance(BookRagRetriever::class, $retriever);
    app()->forgetInstance(\App\Services\Ai\ChatbotService::class);

    fakeGeminiChatSuccess('Gia la 75.000 VND.');

    postChat([
        'session_id' => $sessionId,
        'question' => 'Cuon do gia bao nhieu?',
    ])
        ->assertOk()
        ->assertJsonPath('meta.retrieval.strategy', 'follow_up_context')
        ->assertJsonPath('meta.retrieval.matched', true);
});

test('exact title miss does not fallback to semantically related book', function (): void {
    $decoy = Book::factory()->create([
        'name' => 'Nguoi Sao Choi - Phan 3: Cuoc Tan Cong Cua Doi Quan Sao Hoa',
        'slug' => 'nguoi-sao-choi-phan-3-cuoc-tan-cong-cua-doi-quan-sao-hoa',
        'selling_price' => 105000,
    ]);

    Inventory::factory()->create([
        'book_id' => $decoy->id,
        'quantity' => 10,
        'reserved_quantity' => 0,
    ]);

    $retriever = Mockery::mock(BookRagRetriever::class);
    $retriever->shouldNotReceive('retrieve');
    app()->instance(BookRagRetriever::class, $retriever);
    app()->forgetInstance(\App\Services\Ai\ChatbotService::class);

    fakeGeminiChatSuccess('Minh chua tim thay thong tin phu hop trong du lieu hien co.');

    $sessionId = '550e8400-e29b-41d4-a716-446655440022';

    $response = postChat([
        'session_id' => $sessionId,
        'question' => 'co ban nguoi ve tu sao hoa khong',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.sources', [])
        ->assertJsonPath('meta.retrieval.strategy', 'none')
        ->assertJsonPath('meta.retrieval.matched', false);

    $message = AiChatMessage::query()->where('session_id', $sessionId)->firstOrFail();

    expect($message->retrieved_books)->toBeNull()
        ->and($message->retrieval_matched)->toBeFalse();

    Http::assertSent(function ($request) use ($decoy): bool {
        if (! str_contains($request->url(), ':generateContent')) {
            return false;
        }

        $payload = json_encode($request->data(), JSON_UNESCAPED_UNICODE);

        return is_string($payload)
            && ! str_contains($payload, '[Book #'.$decoy->id.']')
            && str_contains($payload, 'no_relevant_context=true');
    });
});
