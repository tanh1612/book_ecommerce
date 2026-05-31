<?php

use App\Enums\Book\BookFormat;
use App\Enums\Book\BookLanguage;
use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Publisher;
use App\Services\Ai\BookRagDocumentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'ai.rag.embedder_name' => 'gemini_embedding_2_768',
        'ai.rag.embedding_text_max_description_chars' => 50,
    ]);
});

function ragDocumentFactory(): BookRagDocumentFactory
{
    return app(BookRagDocumentFactory::class);
}

test('buildEmbeddingText includes template fields and excludes dynamic catalog metrics', function (): void {
    $publisher = Publisher::factory()->create(['name' => 'NXB Test']);
    $book = Book::factory()->create([
        'name' => 'Sach Giao Tiep',
        'publisher_id' => $publisher->id,
        'selling_price' => 90_000,
        'average_rating' => 4.25,
        'review_count' => 8,
    ]);
    $author = Author::factory()->create(['name' => 'Nguyen Van A']);
    $category = Category::factory()->create(['name' => 'Ky nang']);
    $book->authors()->attach($author);
    $book->categories()->attach($category);
    $book->detail()->update([
        'description' => 'Mo ta   sach   hay',
        'language' => BookLanguage::VI,
        'format' => BookFormat::PAPERBACK,
        'publication_year' => 2024,
    ]);
    Inventory::factory()->create([
        'book_id' => $book->id,
        'quantity' => 10,
        'reserved_quantity' => 3,
    ]);

    $embeddingText = ragDocumentFactory()->buildEmbeddingText($book->fresh());

    expect($embeddingText)->toContain('Ten sach: Sach Giao Tiep')
        ->and($embeddingText)->toContain('Tac gia: Nguyen Van A')
        ->and($embeddingText)->toContain('The loai: Ky nang')
        ->and($embeddingText)->toContain('Mo ta: Mo ta sach hay')
        ->and($embeddingText)->toContain('Nha xuat ban: NXB Test')
        ->and($embeddingText)->toContain('Ngon ngu: Tiếng Việt')
        ->and($embeddingText)->toContain('Hinh thuc: Bìa mềm')
        ->and($embeddingText)->toContain('Nam xuat ban: 2024')
        ->and($embeddingText)->not->toContain('90000')
        ->and($embeddingText)->not->toContain('90000.0')
        ->and($embeddingText)->not->toContain('4.25')
        ->and($embeddingText)->not->toContain('review_count')
        ->and($embeddingText)->not->toContain('available_stock')
        ->and($embeddingText)->not->toContain('in_stock')
        ->and($embeddingText)->not->toContain('Ngon ngu: vi')
        ->and($embeddingText)->not->toContain('Hinh thuc: paperback');
});

test('buildEmbeddingText omits optional lines when detail is missing', function (): void {
    $book = Book::factory()->create([
        'name' => 'Minimal Book',
        'publisher_id' => null,
    ]);
    $book->detail()->delete();

    $embeddingText = ragDocumentFactory()->buildEmbeddingText($book->fresh());

    expect($embeddingText)->toBe('Ten sach: Minimal Book')
        ->and($embeddingText)->not->toContain('Ngon ngu:')
        ->and($embeddingText)->not->toContain('Hinh thuc:')
        ->and($embeddingText)->not->toContain('Mo ta:');
});

test('buildEmbeddingText truncates long descriptions', function (): void {
    $book = Book::factory()->create([
        'name' => 'Long Description Book',
        'publisher_id' => null,
    ]);
    $book->detail()->update([
        'description' => str_repeat('a', 120),
    ]);

    $embeddingText = ragDocumentFactory()->buildEmbeddingText($book->fresh());

    $descriptionLine = collect(explode("\n", $embeddingText))
        ->first(fn (string $line): bool => str_starts_with($line, 'Mo ta: '));

    expect(mb_strlen(str_replace('Mo ta: ', '', (string) $descriptionLine)))->toBe(50);
});

test('makeDocument includes rag metadata and optional vectors', function (): void {
    $publisher = Publisher::factory()->create(['name' => 'NXB Payload']);
    $book = Book::factory()->create([
        'name' => 'Payload Book',
        'publisher_id' => $publisher->id,
        'selling_price' => 120_000,
        'average_rating' => 4.5,
        'review_count' => 20,
    ]);
    $category = Category::factory()->create(['name' => 'Van hoc']);
    $book->categories()->attach($category);
    $book->detail()->update([
        'language' => BookLanguage::EN,
        'format' => BookFormat::HARDCOVER,
        'publication_year' => 2023,
        'num_pages' => 320,
    ]);
    Inventory::factory()->create([
        'book_id' => $book->id,
        'quantity' => 12,
        'reserved_quantity' => 0,
    ]);

    $vector = array_fill(0, 768, 0.01);
    $document = ragDocumentFactory()->makeDocument($book->fresh(), $vector);

    expect($document['name'])->toBe('Payload Book')
        ->and($document['category_names'])->toBe(['Van hoc'])
        ->and($document['publisher_name'])->toBe('NXB Payload')
        ->and($document['language'])->toBe('en')
        ->and($document['language_label'])->toBe('Tiếng Anh')
        ->and($document['format'])->toBe('hardcover')
        ->and($document['format_label'])->toBe('Bìa cứng')
        ->and($document['publication_year'])->toBe(2023)
        ->and($document['num_pages'])->toBe(320)
        ->and($document['selling_price'])->toBe(120000.0)
        ->and($document['average_rating'])->toBe(4.5)
        ->and($document['available_stock'])->toBe(12)
        ->and($document['rag_embedding_text'])->toContain('Ten sach: Payload Book')
        ->and($document['_vectors']['gemini_embedding_2_768'])->toHaveCount(768);
});

test('makeDocument without vector omits _vectors key', function (): void {
    $book = Book::factory()->create(['name' => 'No Vector Book']);

    $document = ragDocumentFactory()->makeDocument($book->fresh());

    expect($document)->not->toHaveKey('_vectors')
        ->and($document)->toHaveKey('rag_embedding_text');
});
