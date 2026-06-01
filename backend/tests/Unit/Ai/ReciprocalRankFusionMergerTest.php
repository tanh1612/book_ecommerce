<?php

use App\Services\Ai\Support\ReciprocalRankFusionMerger;

uses(Tests\TestCase::class);

test('merge assigns rank 1 score of 1 over k plus 1 for a single list', function (): void {
    $scores = (new ReciprocalRankFusionMerger())->merge([[10]], 60);

    expect($scores)->toBe([10 => 1 / 61]);
});

test('merge sums scores when a book appears in multiple lists', function (): void {
    $scores = (new ReciprocalRankFusionMerger())->merge([[10, 20], [10, 30]], 60);

    expect($scores[10])->toBe((1 / 61) + (1 / 61))
        ->and($scores[10])->toBeGreaterThan($scores[20])
        ->and($scores[10])->toBeGreaterThan($scores[30]);
});

test('merge uses one-based ranks within each list', function (): void {
    $scores = (new ReciprocalRankFusionMerger())->merge([[10, 20]], 60);

    expect($scores[10])->toBe(1 / 61)
        ->and($scores[20])->toBe(1 / 62);
});

test('merge ignores empty ranked lists', function (): void {
    $scores = (new ReciprocalRankFusionMerger())->merge([[], [10]], 60);

    expect($scores)->toBe([10 => 1 / 61]);
});
