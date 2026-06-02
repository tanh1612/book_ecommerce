<?php

use App\Services\Ai\AnswerSourceSelector;
use App\Services\Ai\Dto\RetrievedBookPromptContext;

uses(Tests\TestCase::class);

function makeSourceContext(int $bookId, string $name, string $slug = 'slug'): RetrievedBookPromptContext
{
    return new RetrievedBookPromptContext(
        bookId: $bookId,
        name: $name,
        slug: $slug,
        authorNames: [],
        categoryNames: [],
        descriptionShort: '',
        publisherName: null,
        publicationYear: null,
        numPages: null,
        sellingPrice: 100000,
        averageRating: 4.0,
        reviewCount: 10,
        availableStock: 5,
        inStock: true,
        similarityScore: 0.9,
    );
}

test('answer source selector returns only books mentioned in answer', function (): void {
    $contexts = [
        makeSourceContext(1, 'Gioi nhin nguoi, kheo bat chuyen'),
        makeSourceContext(2, 'Thuyet phuc bang ngon ngu co the'),
        makeSourceContext(3, 'Dam thoai thong minh'),
        makeSourceContext(4, 'Giao tiep hieu qua'),
        makeSourceContext(5, 'Nghe thay de hieu'),
    ];

    $selected = app(AnswerSourceSelector::class)->select(
        answer: 'Ban co the xem Gioi nhin nguoi, kheo bat chuyen va Thuyet phuc bang ngon ngu co the.',
        bookContexts: $contexts,
        effectiveMatched: true,
    );

    expect($selected)->toHaveCount(2)
        ->and(array_map(static fn ($context) => $context->bookId, $selected))->toBe([1, 2]);
});

test('answer source selector returns empty when answer mentions no book names', function (): void {
    $contexts = [
        makeSourceContext(1, 'Dac Nhan Tam'),
    ];

    $selected = app(AnswerSourceSelector::class)->select(
        answer: 'Gia sach la 86.000 VND.',
        bookContexts: $contexts,
        effectiveMatched: true,
    );

    expect($selected)->toBe([]);
});

test('answer source selector orders cited books by appearance in answer', function (): void {
    $contexts = [
        makeSourceContext(1, 'Dam thoai thong minh'),
        makeSourceContext(2, 'Gioi nhin nguoi, kheo bat chuyen'),
        makeSourceContext(3, 'Thuyet phuc bang ngon ngu co the'),
    ];

    $selected = app(AnswerSourceSelector::class)->select(
        answer: 'Ban co the xem Gioi nhin nguoi, kheo bat chuyen, Thuyet phuc bang ngon ngu co the va Dam thoai thong minh.',
        bookContexts: $contexts,
        effectiveMatched: true,
    );

    expect(array_map(static fn ($context) => $context->bookId, $selected))->toBe([2, 3, 1]);
});

test('answer source selector ignores a single generic title token', function (): void {
    $contexts = [
        makeSourceContext(1, 'Khu Vuon Doi Tra'),
    ];

    $selected = app(AnswerSourceSelector::class)->select(
        answer: 'Toi chua tim thay thong tin ve cuon Vuon Doc Duoc trong du lieu hien co cua Bookify.',
        bookContexts: $contexts,
        effectiveMatched: true,
    );

    expect($selected)->toBe([]);
});
