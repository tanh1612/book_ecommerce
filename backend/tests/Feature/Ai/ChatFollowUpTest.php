<?php

use App\Models\Book;
use App\Models\Inventory;
use App\Services\Ai\BookRagRetriever;
use App\Services\Ai\ChatContextStore;
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
});

function bindFollowUpBooksRetriever(array $books): void
{
    $documents = array_map(
        static fn (Book $book, int $index): BookRagRetrievedDocument => new BookRagRetrievedDocument(
            (int) $book->id,
            0.9 - ($index * 0.01),
            (string) $book->name,
            (string) $book->slug,
            [],
        ),
        $books,
        array_keys($books),
    );

    $mock = Mockery::mock(BookRagRetriever::class);
    $mock->shouldReceive('retrieve')->andReturn(new BookRagRetrievalResult(
        matched: true,
        topScore: 0.9,
        documents: $documents,
        strategy: 'hybrid',
    ));
    app()->instance(BookRagRetriever::class, $mock);
}

function createFollowUpBook(string $name, string $slug, float $price, int $stock): Book
{
    $book = Book::factory()->create([
        'name' => $name,
        'slug' => $slug,
        'selling_price' => $price,
    ]);

    Inventory::factory()->create([
        'book_id' => $book->id,
        'quantity' => $stock,
        'reserved_quantity' => 0,
    ]);

    return $book;
}

test('follow up first book price uses rewritten retrieval query and cites first source', function (): void {
    $bookA = createFollowUpBook('Gioi nhin nguoi, khéo bat chuyen', 'gioi-nhin-nguoi', 120000, 5);
    $bookB = createFollowUpBook('Thuyet phuc bang ngon ngu co the', 'thuyet-phuc', 98000, 3);
    $bookC = createFollowUpBook('Dam thoai thong minh', 'dam-thoai', 87000, 2);

    bindFollowUpBooksRetriever([$bookA, $bookB, $bookC]);

    $sessionId = '550e8400-e29b-41d4-a716-446655440030';

    fakeGeminiChatSuccess(
        'Ban co the xem Gioi nhin nguoi, khéo bat chuyen, Thuyet phuc bang ngon ngu co the va Dam thoai thong minh.',
    );

    postChat([
        'session_id' => $sessionId,
        'question' => 'Toi muon sach ve ky nang giao tiep',
    ])->assertOk();

    $lastSources = app(ChatContextStore::class)->getLastSources($sessionId);

    expect($lastSources)->toHaveCount(3)
        ->and($lastSources[0]['book_id'])->toBe((int) $bookA->id);

    $retriever = Mockery::mock(BookRagRetriever::class);
    $retriever->shouldNotReceive('retrieve');
    app()->instance(BookRagRetriever::class, $retriever);

    fakeGeminiChatSuccess('Gioi nhin nguoi, khéo bat chuyen co gia 120.000 VND.');

    $response = postChat([
        'session_id' => $sessionId,
        'question' => 'Cuon dau tien gia bao nhieu?',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.sources.0.book_id', $bookA->id)
        ->assertJsonPath('meta.retrieval.strategy', 'follow_up_context')
        ->assertJsonPath('meta.retrieval.matched', true);
});

test('follow up second book stock question targets second last source', function (): void {
    $bookA = createFollowUpBook('Gioi nhin nguoi, khéo bat chuyen', 'gioi-nhin-nguoi-a', 120000, 5);
    $bookB = createFollowUpBook('Thuyet phuc bang ngon ngu co the', 'thuyet-phuc-b', 98000, 0);

    bindFollowUpBooksRetriever([$bookA, $bookB]);

    $sessionId = '550e8400-e29b-41d4-a716-446655440031';

    fakeGeminiChatSuccess(
        'Co sach "Gioi nhin nguoi, khéo bat chuyen" va "Thuyet phuc bang ngon ngu co the".',
    );

    postChat([
        'session_id' => $sessionId,
        'question' => 'Goi y sach giao tiep',
    ])->assertOk();

    expect(app(ChatContextStore::class)->getLastSources($sessionId))->toHaveCount(2);

    $retriever = Mockery::mock(BookRagRetriever::class);
    $retriever->shouldNotReceive('retrieve');
    app()->instance(BookRagRetriever::class, $retriever);

    fakeGeminiChatSuccess('Thuyet phuc bang ngon ngu co the hien het hang.');

    $response = postChat([
        'session_id' => $sessionId,
        'question' => 'Cuon thu hai con hang khong?',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.sources.0.book_id', $bookB->id)
        ->assertJsonPath('meta.retrieval.strategy', 'follow_up_context');
});

test('ordinal follow ups keep original recommendation list for later ordinal questions', function (): void {
    $bookA = createFollowUpBook('First Communication Book', 'first-communication-book-later', 120000, 5);
    $bookB = createFollowUpBook('Second Communication Book', 'second-communication-book-later', 98000, 0);

    $fakeRetriever = new class([$bookA, $bookB]) extends BookRagRetriever
    {
        private int $calls = 0;

        public function __construct(private readonly array $books) {}

        public function retrieve(string $question): BookRagRetrievalResult
        {
            $this->calls++;

            if ($this->calls > 1) {
                throw new RuntimeException('Follow-up should not call RAG retriever.');
            }

            $documents = array_map(
                static fn (Book $book, int $index): BookRagRetrievedDocument => new BookRagRetrievedDocument(
                    (int) $book->id,
                    0.9 - ($index * 0.01),
                    (string) $book->name,
                    (string) $book->slug,
                    [],
                ),
                $this->books,
                array_keys($this->books),
            );

            return new BookRagRetrievalResult(
                matched: true,
                topScore: 0.9,
                documents: $documents,
                strategy: 'hybrid',
            );
        }
    };
    app()->instance(BookRagRetriever::class, $fakeRetriever);

    $sessionId = '550e8400-e29b-41d4-a716-446655440035';

    fakeGeminiChatSuccess('Co sach "First Communication Book" va "Second Communication Book".');

    postChat([
        'session_id' => $sessionId,
        'question' => 'Goi y sach giao tiep',
    ])->assertOk();

    fakeGeminiChatSuccess('First Communication Book con hang.');

    postChat([
        'session_id' => $sessionId,
        'question' => 'Cuon thu nhat con hang khong?',
    ])
        ->assertOk()
        ->assertJsonPath('data.sources.0.book_id', $bookA->id);

    expect(app(ChatContextStore::class)->getLastSources($sessionId))->toHaveCount(2);

    fakeGeminiChatSuccess('Second Communication Book hien het hang.');

    postChat([
        'session_id' => $sessionId,
        'question' => 'con cuon thu 2 thi sao',
    ])
        ->assertOk()
        ->assertJsonPath('data.sources.0.book_id', $bookB->id)
        ->assertJsonPath('meta.retrieval.strategy', 'follow_up_context');
});

test('demonstrative follow up uses most recent resolved source', function (): void {
    $bookA = createFollowUpBook('Nhung Cuoc Phieu Luu Cua Tom Sawyer', 'tom-sawyer', 120000, 5);
    $bookB = createFollowUpBook('Gulliver Phieu Luu Ky', 'gulliver-phieu-luu-ky', 98000, 4);

    $fakeRetriever = new class([$bookA, $bookB]) extends BookRagRetriever
    {
        private int $calls = 0;

        public function __construct(private readonly array $books) {}

        public function retrieve(string $question): BookRagRetrievalResult
        {
            $this->calls++;

            if ($this->calls > 1) {
                throw new RuntimeException('Follow-up should not call RAG retriever.');
            }

            $documents = array_map(
                static fn (Book $book, int $index): BookRagRetrievedDocument => new BookRagRetrievedDocument(
                    (int) $book->id,
                    0.9 - ($index * 0.01),
                    (string) $book->name,
                    (string) $book->slug,
                    [],
                ),
                $this->books,
                array_keys($this->books),
            );

            return new BookRagRetrievalResult(
                matched: true,
                topScore: 0.9,
                documents: $documents,
                strategy: 'hybrid',
            );
        }
    };
    app()->instance(BookRagRetriever::class, $fakeRetriever);

    $sessionId = '550e8400-e29b-41d4-a716-446655440036';

    fakeGeminiChatSuccess('Co sach "Nhung Cuoc Phieu Luu Cua Tom Sawyer" va "Gulliver Phieu Luu Ky".');

    postChat([
        'session_id' => $sessionId,
        'question' => 'goi y cho toi cac cuon sach co chu de phieu luu',
    ])->assertOk();

    fakeGeminiChatSuccess('Gulliver Phieu Luu Ky con hang.');

    postChat([
        'session_id' => $sessionId,
        'question' => 'Cuon thu hai con hang khong?',
    ])
        ->assertOk()
        ->assertJsonPath('data.sources.0.book_id', $bookB->id)
        ->assertJsonPath('meta.retrieval.strategy', 'follow_up_context');

    expect(app(ChatContextStore::class)->getLastSources($sessionId))->toHaveCount(2)
        ->and(app(ChatContextStore::class)->getCurrentSource($sessionId)['book_id'])->toBe((int) $bookB->id);

    fakeGeminiChatSuccess('Gulliver Phieu Luu Ky co rating tot va phu hop neu ban thich truyen phieu luu.');

    postChat([
        'session_id' => $sessionId,
        'question' => 'Cuon day co hay khong?',
    ])
        ->assertOk()
        ->assertJsonPath('data.sources.0.book_id', $bookB->id)
        ->assertJsonPath('meta.retrieval.strategy', 'follow_up_context');
});

test('follow up exact referent excludes extra rag documents from prompt', function (): void {
    $bookA = createFollowUpBook('First Communication Book', 'first-communication-book', 120000, 5);
    $bookB = createFollowUpBook('Second Communication Book', 'second-communication-book', 98000, 0);
    $decoy = createFollowUpBook('Persuasion Decoy Book', 'persuasion-decoy-book', 109000, 4);

    bindFollowUpBooksRetriever([$bookA, $bookB]);

    $sessionId = '550e8400-e29b-41d4-a716-446655440033';

    fakeGeminiChatSuccess('Co sach "First Communication Book" va "Second Communication Book".');

    postChat([
        'session_id' => $sessionId,
        'question' => 'Goi y sach giao tiep',
    ])->assertOk();

    $retriever = Mockery::mock(BookRagRetriever::class);
    $retriever->shouldNotReceive('retrieve');
    app()->instance(BookRagRetriever::class, $retriever);

    fakeGeminiChatSuccess('Second Communication Book hien het hang.');

    postChat([
        'session_id' => $sessionId,
        'question' => 'Cuon thu hai con hang khong?',
    ])->assertOk();

    Http::assertSent(function ($request) use ($bookB, $decoy): bool {
        if (! str_contains($request->url(), ':generateContent')) {
            return false;
        }

        $payload = json_encode($request->data(), JSON_UNESCAPED_UNICODE);

        return is_string($payload)
            && str_contains($payload, '[Book #'.$bookB->id.']')
            && ! str_contains($payload, '[Book #'.$decoy->id.']');
    });
});

test('unresolved ordinal follow up does not fall back to rag random book', function (): void {
    app(ChatContextStore::class)->putLastSources('550e8400-e29b-41d4-a716-446655440034', [
        ['book_id' => 959, 'name' => 'Only Stored Source', 'slug' => 'only-stored-source'],
    ]);

    $retriever = Mockery::mock(BookRagRetriever::class);
    $retriever->shouldNotReceive('retrieve');
    app()->instance(BookRagRetriever::class, $retriever);

    $response = postChat([
        'session_id' => '550e8400-e29b-41d4-a716-446655440034',
        'question' => 'Cuon thu hai con hang khong?',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.sources', [])
        ->assertJsonPath('meta.model', null)
        ->assertJsonPath('meta.retrieval.matched', false)
        ->assertJsonPath('data.answer', fn (string $answer): bool => str_contains($answer, 'chưa xác định'));

    expect(app(ChatContextStore::class)->getLastSources('550e8400-e29b-41d4-a716-446655440034'))->toBe([]);
});

test('follow up without last sources stays on safe no context path', function (): void {
    bindDefaultBookRagRetriever();
    fakeGeminiChatSuccess((string) config('ai.chat.no_context_message'));

    $sessionId = '550e8400-e29b-41d4-a716-446655440032';

    postChat([
        'session_id' => $sessionId,
        'question' => 'Cuon dau tien gia bao nhieu?',
    ])
        ->assertOk()
        ->assertJsonPath('data.sources', [])
        ->assertJsonPath('meta.retrieval.matched', false);
});
