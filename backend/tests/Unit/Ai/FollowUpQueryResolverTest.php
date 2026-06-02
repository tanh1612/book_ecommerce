<?php

use App\Services\Ai\FollowUpQueryResolver;

uses(Tests\TestCase::class);

test('follow up resolver rewrites first book price question', function (): void {
    $lastSources = [
        ['book_id' => 1119, 'name' => 'Gioi nhin nguoi, khéo bat chuyen', 'slug' => 'gioi-nhin-nguoi'],
        ['book_id' => 1670, 'name' => 'Thuyet phuc bang ngon ngu co the', 'slug' => 'thuyet-phuc'],
    ];

    $rewritten = app(FollowUpQueryResolver::class)->resolve(
        'Cuon dau tien gia bao nhieu?',
        $lastSources,
    );

    expect($rewritten)->toBe('Gioi nhin nguoi, khéo bat chuyen bao nhieu');
});

test('follow up resolver rewrites second book stock question', function (): void {
    $lastSources = [
        ['book_id' => 1, 'name' => 'Sach A', 'slug' => 'sach-a'],
        ['book_id' => 2, 'name' => 'Sach B', 'slug' => 'sach-b'],
    ];

    $rewritten = app(FollowUpQueryResolver::class)->resolve(
        'Cuon thu hai con hang khong?',
        $lastSources,
    );

    expect($rewritten)->toBe('Sach B con hang khong');
});

test('follow up resolver rewrites what about second book as stock question', function (): void {
    $lastSources = [
        ['book_id' => 1, 'name' => 'Sach A', 'slug' => 'sach-a'],
        ['book_id' => 2, 'name' => 'Sach B', 'slug' => 'sach-b'],
    ];

    $rewritten = app(FollowUpQueryResolver::class)->resolve(
        'con cuon thu 2 thi sao',
        $lastSources,
    );

    expect($rewritten)->toBe('Sach B con hang khong');
});

test('follow up resolver returns selected source', function (): void {
    $lastSources = [
        ['book_id' => 1, 'name' => 'Sach A', 'slug' => 'sach-a'],
        ['book_id' => 2, 'name' => 'Sach B', 'slug' => 'sach-b'],
    ];

    $source = app(FollowUpQueryResolver::class)->resolveSource(
        'Cuon thu hai con hang khong?',
        $lastSources,
    );

    expect($source)->toBe($lastSources[1]);
});

test('follow up resolver uses current source for demonstrative reference', function (): void {
    $lastSources = [
        ['book_id' => 1, 'name' => 'Sach A', 'slug' => 'sach-a'],
        ['book_id' => 2, 'name' => 'Sach B', 'slug' => 'sach-b'],
    ];
    $currentSource = $lastSources[1];

    $rewritten = app(FollowUpQueryResolver::class)->resolve(
        'Cuon do co hay khong?',
        $lastSources,
        $currentSource,
    );

    expect($rewritten)->toBe('Sach B co hay khong');
});

test('follow up resolver uses current source for day demonstrative reference', function (): void {
    $lastSources = [
        ['book_id' => 1, 'name' => 'Sach A', 'slug' => 'sach-a'],
        ['book_id' => 2, 'name' => 'Sach B', 'slug' => 'sach-b'],
    ];
    $currentSource = $lastSources[1];

    $rewritten = app(FollowUpQueryResolver::class)->resolve(
        'Cuon day co hay khong?',
        $lastSources,
        $currentSource,
    );

    expect($rewritten)->toBe('Sach B co hay khong');
});

test('follow up resolver recognizes vietnamese day demonstrative reference', function (): void {
    $lastSources = [
        ['book_id' => 1, 'name' => 'Sach A', 'slug' => 'sach-a'],
        ['book_id' => 2, 'name' => 'Sach B', 'slug' => 'sach-b'],
    ];
    $currentSource = $lastSources[1];

    $rewritten = app(FollowUpQueryResolver::class)->resolve(
        'Cuốn đấy có hay không?',
        $lastSources,
        $currentSource,
    );

    expect($rewritten)->toBe('Sach B co hay khong');
});

test('follow up resolver keeps ordinal reference on original source list despite current source', function (): void {
    $lastSources = [
        ['book_id' => 1, 'name' => 'Sach A', 'slug' => 'sach-a'],
        ['book_id' => 2, 'name' => 'Sach B', 'slug' => 'sach-b'],
    ];
    $currentSource = $lastSources[1];

    $rewritten = app(FollowUpQueryResolver::class)->resolve(
        'Cuon thu nhat con hang khong?',
        $lastSources,
        $currentSource,
    );

    expect($rewritten)->toBe('Sach A con hang khong');
});

test('follow up resolver returns null without last sources', function (): void {
    $rewritten = app(FollowUpQueryResolver::class)->resolve(
        'Cuon dau tien gia bao nhieu?',
        [],
    );

    expect($rewritten)->toBeNull();
});

test('follow up resolver does not clamp missing ordinal to last source', function (): void {
    $rewritten = app(FollowUpQueryResolver::class)->resolve(
        'Cuon thu hai con hang khong?',
        [
            ['book_id' => 1, 'name' => 'Sach A', 'slug' => 'sach-a'],
        ],
    );

    expect($rewritten)->toBeNull();
});

test('follow up resolver rewrites first ordinal stock question', function (): void {
    $lastSources = [
        ['book_id' => 1, 'name' => 'Sach A', 'slug' => 'sach-a'],
        ['book_id' => 2, 'name' => 'Sach B', 'slug' => 'sach-b'],
    ];

    $rewritten = app(FollowUpQueryResolver::class)->resolve(
        'Cuon thu nhat con hang khong?',
        $lastSources,
    );

    expect($rewritten)->toBe('Sach A con hang khong');
});

test('follow up resolver rewrites what about first book as stock question', function (): void {
    $lastSources = [
        ['book_id' => 1, 'name' => 'Sach A', 'slug' => 'sach-a'],
        ['book_id' => 2, 'name' => 'Sach B', 'slug' => 'sach-b'],
    ];

    $rewritten = app(FollowUpQueryResolver::class)->resolve(
        'con cuon thu 1 thi sao',
        $lastSources,
    );

    expect($rewritten)->toBe('Sach A con hang khong');
});

test('follow up resolver rewrites third book quality question', function (): void {
    $lastSources = [
        ['book_id' => 1, 'name' => 'Sach A', 'slug' => 'sach-a'],
        ['book_id' => 2, 'name' => 'Sach B', 'slug' => 'sach-b'],
        ['book_id' => 3, 'name' => 'Sach C', 'slug' => 'sach-c'],
    ];

    $rewritten = app(FollowUpQueryResolver::class)->resolve(
        'Cuon thu 3 co hay khong?',
        $lastSources,
    );

    expect($rewritten)->toBe('Sach C co hay khong');
});
