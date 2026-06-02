<?php

use App\Services\Ai\Dto\RetrievedBookPromptContext;
use App\Services\Ai\Support\AnswerBookSegmenter;
use App\Services\Ai\Support\BookMentionMatcher;

uses(Tests\TestCase::class);

function makeSegmentContext(int $bookId, string $name): RetrievedBookPromptContext
{
    return new RetrievedBookPromptContext(
        bookId: $bookId,
        name: $name,
        slug: 'slug-'.$bookId,
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

test('answer book segmenter attributes price to nearest book in shared sentence', function (): void {
    $matcher = app(BookMentionMatcher::class);
    $segmenter = app(AnswerBookSegmenter::class);
    $matchingText = $matcher->normalizeForMatching(
        'Gia lan luot la 100.000 VND cho Alpha Book va 200.000 VND cho Beta Book.',
    );

    $contexts = [
        makeSegmentContext(1, 'Alpha Book'),
        makeSegmentContext(2, 'Beta Book'),
    ];

    $firstPriceOffset = (int) mb_strpos($matchingText, '100');
    $secondPriceOffset = (int) mb_strpos($matchingText, '200');

    expect($segmenter->attributeClaimOffset($firstPriceOffset, $matchingText, $contexts)?->bookId)->toBe(1)
        ->and($segmenter->attributeClaimOffset($secondPriceOffset, $matchingText, $contexts)?->bookId)->toBe(2);
});

test('answer book segmenter returns null for claim before first cited book when multiple cited', function (): void {
    $matcher = app(BookMentionMatcher::class);
    $segmenter = app(AnswerBookSegmenter::class);
    $matchingText = $matcher->normalizeForMatching('Gia 100.000 VND. Alpha Book va Beta Book.');
    $contexts = [
        makeSegmentContext(1, 'Alpha Book'),
        makeSegmentContext(2, 'Beta Book'),
    ];

    $priceOffset = (int) mb_strpos($matchingText, '100');

    expect($segmenter->attributeClaimOffset($priceOffset, $matchingText, $contexts))->toBeNull();
});
