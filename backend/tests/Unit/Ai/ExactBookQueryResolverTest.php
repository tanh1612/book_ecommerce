<?php

use App\Models\Book;
use App\Services\Ai\ExactBookQueryResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

test('exact book resolver returns mysql match ahead of unrelated titles', function (): void {
    $target = Book::factory()->create([
        'name' => 'Bắt Tiên Xóm Con Chuột',
        'slug' => 'bat-tien-xom-con-chuot',
    ]);

    Book::factory()->create([
        'name' => 'Chuột Đồng Cổ Tích',
        'slug' => 'chuot-dong-co-tich',
    ]);

    $documents = app(ExactBookQueryResolver::class)->resolveToDocuments(
        'Sách Bắt Tiên Xóm Con Chuột còn hàng không?',
    );

    expect($documents)->not->toBeEmpty()
        ->and($documents[0]->bookId)->toBe((int) $target->id);
});

test('exact book resolver ignores questions without price or stock intent', function (): void {
    Book::factory()->create([
        'name' => 'Bắt Tiên Xóm Con Chuột',
        'slug' => 'bat-tien-xom-con-chuot',
    ]);

    $documents = app(ExactBookQueryResolver::class)->resolveToDocuments(
        'Toi muon tim sach ve ky nang giao tiep',
    );

    expect($documents)->toBe([]);
});

test('exact book resolver handles sell availability intent with partial series title', function (): void {
    $volumeTwo = Book::factory()->create([
        'name' => 'Vuon Doc Duoc 2: Dau Vet Cua Toi Ac',
        'slug' => 'vuon-doc-duoc-2-dau-vet-cua-toi-ac',
    ]);

    $volumeOne = Book::factory()->create([
        'name' => 'Vuon Doc Duoc 1: 9 Ky An Chan Dong The Gioi',
        'slug' => 'vuon-doc-duoc-1-9-ky-an-chan-dong-the-gioi',
    ]);

    Book::factory()->create([
        'name' => 'Khu Vuon Doi Tra',
        'slug' => 'khu-vuon-doi-tra',
    ]);

    $documents = app(ExactBookQueryResolver::class)->resolveToDocuments(
        'co ban sach vuon doc duoc khong',
    );

    expect($documents)->toHaveCount(2)
        ->and(array_map(static fn ($document): int => $document->bookId, $documents))
        ->toEqualCanonicalizing([(int) $volumeTwo->id, (int) $volumeOne->id]);
});

test('exact book resolver requires exact match for title lookup but not topic availability', function (): void {
    $resolver = app(ExactBookQueryResolver::class);

    expect($resolver->requiresExactMatch('co ban nguoi ve tu sao hoa khong'))->toBeTrue()
        ->and($resolver->requiresExactMatch('co ban sach ve sao hoa khong'))->toBeFalse()
        ->and($resolver->requiresExactMatch('Toi muon sach ve ky nang giao tiep'))->toBeFalse();
});
