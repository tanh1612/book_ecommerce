<?php

use App\Enums\Promotion\PromotionStatus;
use App\Models\Book;
use App\Models\Inventory;
use App\Models\Promotion;
use App\Models\PromotionItem;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    if (! Warehouse::query()->exists()) {
        Warehouse::factory()->create();
    }
});

function flashSaleBookWithStock(array $attributes = []): Book
{
    $book = Book::factory()->create($attributes);

    Inventory::factory()->create([
        'book_id' => $book->id,
        'warehouse_id' => (int) Warehouse::query()->firstOrFail()->id,
        'quantity' => 10,
        'reserved_quantity' => 0,
    ]);

    return $book;
}

test('books index does not expose promotion pricing fields', function (): void {
    $book = flashSaleBookWithStock(['selling_price' => 200000]);

    Promotion::query()->create([
        'name' => 'Active flash',
        'type' => 'flash_sale',
        'start_at' => now()->subMinute(),
        'end_at' => now()->addHour(),
        'status' => PromotionStatus::ACTIVE,
    ])->items()->create([
        'book_id' => $book->id,
        'discount_value' => 40,
    ]);

    $response = $this->getJson('/api/v1/books');

    $response->assertOk();
    $row = collect($response->json('data'))->firstWhere('id', $book->id);
    expect($row)->not->toBeNull()
        ->and($row)->toHaveKey('selling_price')
        ->and((float) $row['selling_price'])->toBe(200000.0)
        ->and($row)->not->toHaveKeys(['effective_price', 'discount_amount', 'promotion']);
});

test('flash sales active endpoint returns active campaign and discounted items', function (): void {
    $book = flashSaleBookWithStock(['selling_price' => 100000]);

    $campaign = Promotion::query()->create([
        'name' => 'Flash zone',
        'type' => 'flash_sale',
        'start_at' => now()->subMinute(),
        'end_at' => now()->addHour(),
        'status' => PromotionStatus::ACTIVE,
    ]);

    $item = PromotionItem::query()->create([
        'promotion_id' => $campaign->id,
        'book_id' => $book->id,
        'discount_value' => 20,
        'stock_limit' => 10,
        'sold_quantity' => 2,
    ]);

    $this->getJson('/api/v1/flash-sales/active')
        ->assertOk()
        ->assertJsonPath('data.id', $campaign->id)
        ->assertJsonMissingPath('data.name')
        ->assertJsonPath('data.items.0.promotion_item_id', $item->id)
        ->assertJsonPath('data.items.0.book.id', $book->id)
        ->assertJsonPath('data.items.0.discount_percent', 20)
        ->assertJsonPath('data.items.0.remaining_stock', 8)
        ->assertJsonMissingPath('data.items.0.flash_sale_price')
        ->assertJsonMissingPath('data.items.0.max_quantity_per_user');
});

test('flash sales active endpoint switches to adjacent scheduled campaign at exact boundary without status sync', function (): void {
    $firstBook = flashSaleBookWithStock();
    $secondBook = flashSaleBookWithStock();
    $boundary = Carbon::parse('2026-05-27 15:00:00');

    $first = Promotion::query()->create([
        'name' => '13h to 15h',
        'type' => 'flash_sale',
        'start_at' => $boundary->copy()->subHours(2),
        'end_at' => $boundary,
        'status' => PromotionStatus::ACTIVE,
    ]);
    $first->items()->create([
        'book_id' => $firstBook->id,
        'discount_value' => 10,
    ]);

    $second = Promotion::query()->create([
        'name' => '15h to 17h',
        'type' => 'flash_sale',
        'start_at' => $boundary,
        'end_at' => $boundary->copy()->addHours(2),
        'status' => PromotionStatus::SCHEDULED,
    ]);
    $second->items()->create([
        'book_id' => $secondBook->id,
        'discount_value' => 20,
    ]);

    Carbon::setTestNow($boundary);

    try {
        $this->getJson('/api/v1/flash-sales/active')
            ->assertOk()
            ->assertJsonPath('data.id', $second->id)
            ->assertJsonPath('data.items.0.book.id', $secondBook->id)
            ->assertJsonPath('data.items.0.discount_percent', 20);
    } finally {
        Carbon::setTestNow();
    }
});

test('flash sales active endpoint returns null when no active campaign', function (): void {
    $this->getJson('/api/v1/flash-sales/active')
        ->assertOk()
        ->assertJsonPath('data', null);
});

test('flash sales active endpoint excludes scheduled and expired campaigns', function (): void {
    $book = flashSaleBookWithStock();

    Promotion::query()->create([
        'name' => 'Scheduled',
        'type' => 'flash_sale',
        'start_at' => now()->addDay(),
        'end_at' => now()->addDays(2),
        'status' => PromotionStatus::SCHEDULED,
    ])->items()->create([
        'book_id' => $book->id,
        'discount_value' => 10,
    ]);

    Promotion::query()->create([
        'name' => 'Expired',
        'type' => 'flash_sale',
        'start_at' => now()->subDays(2),
        'end_at' => now()->subDay(),
        'status' => PromotionStatus::EXPIRED,
    ])->items()->create([
        'book_id' => $book->id,
        'discount_value' => 10,
    ]);

    $this->getJson('/api/v1/flash-sales/active')
        ->assertOk()
        ->assertJsonPath('data', null);
});

test('flash sales active endpoint excludes sold out items', function (): void {
    $book = flashSaleBookWithStock();

    $campaign = Promotion::query()->create([
        'name' => 'Flash',
        'type' => 'flash_sale',
        'start_at' => now()->subMinute(),
        'end_at' => now()->addHour(),
        'status' => PromotionStatus::ACTIVE,
    ]);

    PromotionItem::query()->create([
        'promotion_id' => $campaign->id,
        'book_id' => $book->id,
        'discount_value' => 10,
        'stock_limit' => 5,
        'sold_quantity' => 5,
    ]);

    $this->getJson('/api/v1/flash-sales/active')
        ->assertOk()
        ->assertJsonPath('data.items', []);
});

test('flash sales active endpoint ignores legacy discount type rows', function (): void {
    $book = flashSaleBookWithStock();
    $now = now();

    $legacyId = DB::table('promotions')->insertGetId([
        'name' => 'Legacy regular',
        'type' => 'discount',
        'start_at' => $now->copy()->subMinute(),
        'end_at' => $now->copy()->addHour(),
        'status' => PromotionStatus::ACTIVE->value,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('promotion_items')->insert([
        'promotion_id' => $legacyId,
        'book_id' => $book->id,
        'discount_value' => 50,
        'sold_quantity' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $this->getJson('/api/v1/flash-sales/active')
        ->assertOk()
        ->assertJsonPath('data', null);
});

test('book detail returns flash sale payload for active item', function (): void {
    $book = flashSaleBookWithStock([
        'slug' => 'flash-book',
        'selling_price' => 100000,
    ]);

    $campaign = Promotion::query()->create([
        'name' => 'Detail flash',
        'type' => 'flash_sale',
        'start_at' => now()->subMinute(),
        'end_at' => now()->addHour(),
        'status' => PromotionStatus::ACTIVE,
    ]);

    $item = PromotionItem::query()->create([
        'promotion_id' => $campaign->id,
        'book_id' => $book->id,
        'discount_value' => 25,
        'max_quantity_per_user' => 2,
    ]);

    $this->getJson('/api/v1/books/flash-book')
        ->assertOk()
        ->assertJsonPath('data.selling_price', '100000.00')
        ->assertJsonPath('data.flash_sale.promotion_item_id', $item->id)
        ->assertJsonPath('data.flash_sale.discount_percent', 25)
        ->assertJsonMissingPath('data.flash_sale.price')
        ->assertJsonMissingPath('data.flash_sale.max_quantity_per_user')
        ->assertJsonMissing(['data.effective_price', 'data.promotion']);
});

test('book detail returns null flash sale when item is sold out', function (): void {
    $book = flashSaleBookWithStock(['slug' => 'sold-out-flash']);

    Promotion::query()->create([
        'name' => 'Sold out',
        'type' => 'flash_sale',
        'start_at' => now()->subMinute(),
        'end_at' => now()->addHour(),
        'status' => PromotionStatus::ACTIVE,
    ])->items()->create([
        'book_id' => $book->id,
        'discount_value' => 10,
        'stock_limit' => 1,
        'sold_quantity' => 1,
    ]);

    $this->getJson('/api/v1/books/sold-out-flash')
        ->assertOk()
        ->assertJsonPath('data.flash_sale', null);
});

test('flash sales active endpoint excludes inactive books and zero inventory', function (): void {
    $inactiveBook = flashSaleBookWithStock(['is_active' => false]);
    $noStockBook = Book::factory()->create();
    $sellableBook = flashSaleBookWithStock();

    $campaign = Promotion::query()->create([
        'name' => 'Flash filter',
        'type' => 'flash_sale',
        'start_at' => now()->subMinute(),
        'end_at' => now()->addHour(),
        'status' => PromotionStatus::ACTIVE,
    ]);

    foreach ([$inactiveBook, $noStockBook, $sellableBook] as $book) {
        PromotionItem::query()->create([
            'promotion_id' => $campaign->id,
            'book_id' => $book->id,
            'discount_value' => 10,
        ]);
    }

    $this->getJson('/api/v1/flash-sales/active')
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.book.id', $sellableBook->id);
});

test('flash sales active endpoint caps remaining stock by inventory', function (): void {
    $book = flashSaleBookWithStock();
    Inventory::query()->where('book_id', $book->id)->update([
        'quantity' => 3,
        'reserved_quantity' => 0,
    ]);

    $campaign = Promotion::query()->create([
        'name' => 'Flash cap',
        'type' => 'flash_sale',
        'start_at' => now()->subMinute(),
        'end_at' => now()->addHour(),
        'status' => PromotionStatus::ACTIVE,
    ]);

    PromotionItem::query()->create([
        'promotion_id' => $campaign->id,
        'book_id' => $book->id,
        'discount_value' => 10,
        'stock_limit' => 100,
        'sold_quantity' => 0,
    ]);

    $this->getJson('/api/v1/flash-sales/active')
        ->assertOk()
        ->assertJsonPath('data.items.0.remaining_stock', 3);
});
