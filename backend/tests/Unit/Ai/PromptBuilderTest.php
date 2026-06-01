<?php

use App\Services\Ai\Dto\BookRagRetrievalResult;
use App\Services\Ai\Dto\RetrievedBookPromptContext;
use App\Services\Ai\PromptBuilder;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    config([
        'ai.chat.no_context_message' => 'Minh chua tim thay thong tin phu hop trong du lieu hien co.',
    ]);
});

function sampleBookContext(int $bookId = 10, bool $inStock = true): RetrievedBookPromptContext
{
    return new RetrievedBookPromptContext(
        bookId: $bookId,
        name: 'Dac Nhan Tam',
        slug: 'dac-nhan-tam',
        authorNames: ['Dale Carnegie'],
        categoryNames: ['Ky nang song'],
        descriptionShort: 'Sach ve ky nang giao tiep.',
        publisherName: 'NXB Tre',
        publicationYear: 2019,
        numPages: 320,
        sellingPrice: 86000,
        averageRating: 4.5,
        reviewCount: 120,
        availableStock: $inStock ? 5 : 0,
        inStock: $inStock,
        similarityScore: 0.82,
    );
}

function matchedRetrieval(): BookRagRetrievalResult
{
    return new BookRagRetrievalResult(
        matched: true,
        topScore: 0.82,
        documents: [],
        strategy: 'hybrid',
    );
}

function unmatchedRetrieval(string $strategy = 'hybrid'): BookRagRetrievalResult
{
    return new BookRagRetrievalResult(
        matched: false,
        topScore: 0.40,
        documents: [],
        strategy: $strategy,
    );
}

test('build includes retrieved context when matched with book contexts', function (): void {
    $result = app(PromptBuilder::class)->build(
        question: 'Dac Nhan Tam gia bao nhieu?',
        history: [],
        retrieval: matchedRetrieval(),
        bookContexts: [sampleBookContext()],
    );

    expect($result->noRelevantContext)->toBeFalse()
        ->and($result->userText)->toContain('## Retrieved context')
        ->and($result->userText)->toContain('[Book #10]')
        ->and($result->userText)->toContain('Gia ban: 86.000 VND')
        ->and($result->userText)->toContain('Con hang: co')
        ->and($result->userText)->toContain('no_relevant_context=false');
});

test('build marks no relevant context when retrieval is unmatched', function (): void {
    $result = app(PromptBuilder::class)->build(
        question: 'Bookify co ban dien thoai khong?',
        history: [],
        retrieval: unmatchedRetrieval(),
        bookContexts: [],
    );

    expect($result->noRelevantContext)->toBeTrue()
        ->and($result->userText)->toContain('no_relevant_context=true')
        ->and($result->userText)->toContain('du lieu hien co')
        ->and($result->userText)->toContain('Khong khang dinh rang Bookify chac chan khong ban san pham do')
        ->and($result->userText)->not->toContain('[Book #');
});

test('build marks no relevant context when matched but db contexts are empty', function (): void {
    $result = app(PromptBuilder::class)->build(
        question: 'Tim sach',
        history: [],
        retrieval: matchedRetrieval(),
        bookContexts: [],
    );

    expect($result->noRelevantContext)->toBeTrue()
        ->and($result->userText)->toContain('no_relevant_context=true');
});

test('build renders all provided history entries', function (): void {
    $history = [
        ['role' => 'user', 'content' => 'User 1', 'created_at' => now()->toIso8601String()],
        ['role' => 'assistant', 'content' => 'Assistant 1', 'created_at' => now()->toIso8601String()],
        ['role' => 'user', 'content' => 'User 2', 'created_at' => now()->toIso8601String()],
        ['role' => 'assistant', 'content' => 'Assistant 2', 'created_at' => now()->toIso8601String()],
    ];

    $result = app(PromptBuilder::class)->build(
        question: 'Cau hoi moi',
        history: $history,
        retrieval: unmatchedRetrieval(),
        bookContexts: [],
    );

    expect($result->userText)->toContain('User 1')
        ->and($result->userText)->toContain('User 2')
        ->and($result->userText)->toContain('Assistant 2');
});

test('build shows out of stock label in retrieved context', function (): void {
    $result = app(PromptBuilder::class)->build(
        question: 'Con hang khong?',
        history: [],
        retrieval: matchedRetrieval(),
        bookContexts: [sampleBookContext(inStock: false)],
    );

    expect($result->userText)->toContain('Con hang: khong');
});
