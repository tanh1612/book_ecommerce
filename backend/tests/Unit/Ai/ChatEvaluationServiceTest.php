<?php

use App\Services\Ai\ChatEvaluationService;
use App\Services\Ai\Dto\RetrievedBookPromptContext;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    config([
        'ai.chat.no_context_message' => 'Minh chua tim thay thong tin phu hop trong du lieu hien co.',
    ]);
});

function makeEvalBookContext(array $overrides = []): RetrievedBookPromptContext
{
    return new RetrievedBookPromptContext(
        bookId: $overrides['bookId'] ?? 10,
        name: $overrides['name'] ?? 'Dac Nhan Tam',
        slug: $overrides['slug'] ?? 'dac-nhan-tam',
        authorNames: $overrides['authorNames'] ?? ['Dale Carnegie'],
        categoryNames: $overrides['categoryNames'] ?? ['Ky nang'],
        descriptionShort: $overrides['descriptionShort'] ?? 'Mo ta ngan.',
        publisherName: $overrides['publisherName'] ?? 'NXB Tre',
        publicationYear: $overrides['publicationYear'] ?? 1936,
        numPages: $overrides['numPages'] ?? 320,
        sellingPrice: $overrides['sellingPrice'] ?? 120000.0,
        averageRating: $overrides['averageRating'] ?? 4.5,
        reviewCount: $overrides['reviewCount'] ?? 100,
        availableStock: $overrides['availableStock'] ?? 5,
        inStock: $overrides['inStock'] ?? true,
        similarityScore: $overrides['similarityScore'] ?? 0.82,
    );
}

test('answer mentioning book name in context passes evaluation', function (): void {
    $contexts = [makeEvalBookContext()];

    $result = app(ChatEvaluationService::class)->evaluate(
        question: 'Tim sach hay',
        answer: 'Ban co the tham khao Dac Nhan Tam cua Dale Carnegie.',
        retrievalMatched: true,
        bookContexts: $contexts,
    );

    expect($result->verdict)->toBe('pass')
        ->and($result->hasHallucinationRisk)->toBeFalse()
        ->and($result->groundednessScore)->toBeGreaterThanOrEqual(0.7);
});

test('answer price matching structured facts does not flag hallucination risk', function (): void {
    $contexts = [makeEvalBookContext(['sellingPrice' => 120000.0])];

    $result = app(ChatEvaluationService::class)->evaluate(
        question: 'Gia bao nhieu?',
        answer: 'Gia sach la 120.000 VND.',
        retrievalMatched: true,
        bookContexts: $contexts,
    );

    expect($result->hasHallucinationRisk)->toBeFalse()
        ->and($result->riskFlags)->not->toContain('ungrounded_price');
});

test('answer price not in facts flags risk and warning or fail verdict', function (): void {
    $contexts = [makeEvalBookContext(['sellingPrice' => 120000.0])];

    $result = app(ChatEvaluationService::class)->evaluate(
        question: 'Gia bao nhieu?',
        answer: 'Gia sach la 99.000 VND.',
        retrievalMatched: true,
        bookContexts: $contexts,
    );

    expect($result->hasHallucinationRisk)->toBeTrue()
        ->and($result->riskFlags)->toContain('ungrounded_price')
        ->and($result->verdict)->toBeIn(['warning', 'fail']);
});

test('low context answer acknowledging missing data passes', function (): void {
    $result = app(ChatEvaluationService::class)->evaluate(
        question: 'Co ban dien thoai khong?',
        answer: 'Minh chua tim thay thong tin phu hop trong du lieu hien co.',
        retrievalMatched: false,
        bookContexts: [],
    );

    expect($result->verdict)->toBe('pass')
        ->and($result->relevanceScore)->toBeGreaterThanOrEqual(0.85)
        ->and($result->hasHallucinationRisk)->toBeFalse();
});

test('low context answer with vietnamese diacritics acknowledges missing data', function (): void {
    $result = app(ChatEvaluationService::class)->evaluate(
        question: 'Co ban dien thoai khong?',
        answer: 'Mình chưa tìm thấy thông tin phù hợp trong dữ liệu hiện có.',
        retrievalMatched: false,
        bookContexts: [],
    );

    expect($result->verdict)->toBe('pass')
        ->and($result->relevanceScore)->toBeGreaterThanOrEqual(0.85)
        ->and($result->hasHallucinationRisk)->toBeFalse();
});

test('low context answer inventing specific book recommendation fails', function (): void {
    $result = app(ChatEvaluationService::class)->evaluate(
        question: 'Co sach nao hay?',
        answer: 'Ban nen mua sach ABC voi gia 50.000 VND.',
        retrievalMatched: false,
        bookContexts: [],
    );

    expect($result->verdict)->toBe('fail')
        ->and($result->hasHallucinationRisk)->toBeTrue()
        ->and($result->relevanceScore)->toBeLessThanOrEqual(0.45);
});

test('matched context with wrong year flags ungrounded year', function (): void {
    $contexts = [makeEvalBookContext(['publicationYear' => 1936])];

    $result = app(ChatEvaluationService::class)->evaluate(
        question: 'Nam xuat ban?',
        answer: 'Sach xuat ban nam 2020.',
        retrievalMatched: true,
        bookContexts: $contexts,
    );

    expect($result->riskFlags)->toContain('ungrounded_year')
        ->and($result->hasHallucinationRisk)->toBeTrue();
});

test('publication year and plain vnd price do not trigger isbn hallucination flags', function (): void {
    $contexts = [makeEvalBookContext(['publicationYear' => 1936, 'sellingPrice' => 120000.0])];

    $result = app(ChatEvaluationService::class)->evaluate(
        question: 'Thong tin sach?',
        answer: 'Sach xuat ban nam 1936. Gia sach la 120000 VND.',
        retrievalMatched: true,
        bookContexts: $contexts,
    );

    expect($result->riskFlags)->not->toContain('ungrounded_isbn')
        ->and($result->hasHallucinationRisk)->toBeFalse();
});

test('stock answer mentioning only book name keeps high groundedness without author', function (): void {
    $contexts = [makeEvalBookContext([
        'name' => 'Dac Nhan Tam',
        'authorNames' => ['Dale Carnegie'],
        'availableStock' => 3,
    ])];

    $result = app(ChatEvaluationService::class)->evaluate(
        question: 'Cuon nay con hang khong?',
        answer: 'Dac Nhan Tam hien van con hang.',
        retrievalMatched: true,
        bookContexts: $contexts,
    );

    expect($result->hasHallucinationRisk)->toBeFalse()
        ->and($result->groundednessScore)->toBeGreaterThanOrEqual(0.7)
        ->and($result->verdict)->toBe('pass');
});

test('correct price for cited book in context does not lower groundedness when author omitted', function (): void {
    $contexts = [makeEvalBookContext([
        'name' => 'Giao Tiep Thong Minh',
        'authorNames' => ['John Doe'],
        'sellingPrice' => 86000.0,
    ])];

    $result = app(ChatEvaluationService::class)->evaluate(
        question: 'Gia bao nhieu?',
        answer: 'Giao Tiep Thong Minh co gia 86.000 VND.',
        retrievalMatched: true,
        bookContexts: $contexts,
    );

    expect($result->hasHallucinationRisk)->toBeFalse()
        ->and($result->riskFlags)->not->toContain('ungrounded_price')
        ->and($result->groundednessScore)->toBeGreaterThanOrEqual(0.7)
        ->and($result->verdict)->toBe('pass');
});

test('year appearing in book title is not flagged as ungrounded', function (): void {
    $contexts = [makeEvalBookContext([
        'name' => 'Cuoc Tham Hiem Vao Long Dat (Tai Ban 2025)',
        'publicationYear' => null,
    ])];

    $result = app(ChatEvaluationService::class)->evaluate(
        question: 'Thong tin sach?',
        answer: 'Cuoc Tham Hiem Vao Long Dat (Tai Ban 2025) dang co san.',
        retrievalMatched: true,
        bookContexts: $contexts,
    );

    expect($result->riskFlags)->not->toContain('ungrounded_year')
        ->and($result->hasHallucinationRisk)->toBeFalse();
});

test('quality intent question with price only answer fails for intent mismatch', function (): void {
    $contexts = [makeEvalBookContext([
        'name' => 'Cuoc Tham Hiem Vao Long Dat',
        'sellingPrice' => 150000.0,
    ])];

    $result = app(ChatEvaluationService::class)->evaluate(
        question: 'Cuon nay co hay khong?',
        answer: 'Cuoc Tham Hiem Vao Long Dat co gia 150.000 VND.',
        retrievalMatched: true,
        bookContexts: $contexts,
    );

    expect($result->verdict)->toBe('fail')
        ->and($result->relevanceScore)->toBeLessThanOrEqual(0.25)
        ->and($result->riskFlags)->not->toContain('ungrounded_year');
});

test('book price before aggregate total is still validated when total mentions tong cong', function (): void {
    $contexts = [
        makeEvalBookContext([
            'bookId' => 1,
            'name' => 'Alpha Communication Book',
            'sellingPrice' => 120000.0,
        ]),
    ];

    $result = app(ChatEvaluationService::class)->evaluate(
        question: 'Gia sach?',
        answer: 'Alpha Communication Book co gia 98.000 VND. Tong cong 218.000 VND.',
        retrievalMatched: true,
        bookContexts: $contexts,
    );

    expect($result->riskFlags)->toContain('ungrounded_price')
        ->and($result->hasHallucinationRisk)->toBeTrue();
});

test('aggregate total after book prices is ignored when tong cong precedes the amount', function (): void {
    $contexts = [
        makeEvalBookContext([
            'bookId' => 1,
            'name' => 'Alpha Communication Book',
            'sellingPrice' => 120000.0,
        ]),
        makeEvalBookContext([
            'bookId' => 2,
            'name' => 'Beta Communication Book',
            'sellingPrice' => 98000.0,
        ]),
    ];

    $result = app(ChatEvaluationService::class)->evaluate(
        question: 'Gia sach?',
        answer: 'Alpha Communication Book co gia 120.000 VND. Beta Communication Book co gia 98.000 VND. Tong cong 218.000 VND.',
        retrievalMatched: true,
        bookContexts: $contexts,
    );

    expect($result->hasHallucinationRisk)->toBeFalse()
        ->and($result->riskFlags)->not->toContain('ungrounded_price');
});

test('swapped prices between two cited books flag ungrounded price', function (): void {
    $contexts = [
        makeEvalBookContext([
            'bookId' => 1,
            'name' => 'Alpha Communication Book',
            'sellingPrice' => 120000.0,
        ]),
        makeEvalBookContext([
            'bookId' => 2,
            'name' => 'Beta Communication Book',
            'sellingPrice' => 98000.0,
        ]),
    ];

    $result = app(ChatEvaluationService::class)->evaluate(
        question: 'Gia tung cuon?',
        answer: 'Alpha Communication Book co gia 98.000 VND. Beta Communication Book co gia 120.000 VND.',
        retrievalMatched: true,
        bookContexts: $contexts,
    );

    expect($result->riskFlags)->toContain('ungrounded_price')
        ->and($result->hasHallucinationRisk)->toBeTrue()
        ->and($result->verdict)->toBeIn(['warning', 'fail']);
});

test('correct prices for two cited books pass when context order differs from answer', function (): void {
    $contexts = [
        makeEvalBookContext([
            'bookId' => 2,
            'name' => 'Beta Communication Book',
            'sellingPrice' => 98000.0,
        ]),
        makeEvalBookContext([
            'bookId' => 1,
            'name' => 'Alpha Communication Book',
            'sellingPrice' => 120000.0,
        ]),
    ];

    $result = app(ChatEvaluationService::class)->evaluate(
        question: 'Gia tung cuon?',
        answer: 'Alpha Communication Book co gia 120.000 VND. Beta Communication Book co gia 98.000 VND.',
        retrievalMatched: true,
        bookContexts: $contexts,
    );

    expect($result->hasHallucinationRisk)->toBeFalse()
        ->and($result->riskFlags)->not->toContain('ungrounded_price')
        ->and($result->verdict)->toBe('pass');
});

test('vietnamese dong and nghin price formats match structured facts', function (string $answer): void {
    $contexts = [makeEvalBookContext(['sellingPrice' => 120000.0])];

    $result = app(ChatEvaluationService::class)->evaluate(
        question: 'Gia bao nhieu?',
        answer: $answer,
        retrievalMatched: true,
        bookContexts: $contexts,
    );

    expect($result->hasHallucinationRisk)->toBeFalse()
        ->and($result->riskFlags)->not->toContain('ungrounded_price');
})->with([
    'dotted dong' => 'Gia sach la 120.000 đồng.',
    'plain dong' => 'Gia sach la 120000 đồng.',
    'nghin' => 'Gia khoang 120 nghìn.',
    'd sign' => 'Gia sach 120.000 đ.',
]);
