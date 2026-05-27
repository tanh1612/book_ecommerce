<?php

use App\Enums\Promotion\PromotionAllocationStatus;
use App\Models\Account;
use App\Models\Book;
use App\Models\Promotion;
use App\Models\PromotionAllocation;
use App\Models\PromotionItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('promotion item rejects duplicate book per promotion', function (): void {
    $promotion = Promotion::query()->create([
        'name' => 'Flash test',
        'type' => 'flash_sale',
        'start_at' => now()->addDay(),
        'end_at' => now()->addDays(2),
        'status' => 'scheduled',
    ]);

    $book = Book::factory()->create();

    PromotionItem::query()->create([
        'promotion_id' => $promotion->id,
        'book_id' => $book->id,
        'discount_value' => 10,
    ]);

    expect(fn () => PromotionItem::query()->create([
        'promotion_id' => $promotion->id,
        'book_id' => $book->id,
        'discount_value' => 15,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

test('promotion item rejects discount outside percent range', function (): void {
    $promotion = Promotion::query()->create([
        'name' => 'Flash test',
        'type' => 'flash_sale',
        'start_at' => now()->addDay(),
        'end_at' => now()->addDays(2),
        'status' => 'scheduled',
    ]);

    $book = Book::factory()->create();

    expect(fn () => PromotionItem::query()->create([
        'promotion_id' => $promotion->id,
        'book_id' => $book->id,
        'discount_value' => 101,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

test('promotion item rejects fractional discount percent', function (): void {
    $promotion = Promotion::query()->create([
        'name' => 'Flash integer only',
        'type' => 'flash_sale',
        'start_at' => now()->addDay(),
        'end_at' => now()->addDays(2),
        'status' => 'scheduled',
    ]);

    expect(fn () => PromotionItem::query()->create([
        'promotion_id' => $promotion->id,
        'book_id' => Book::factory()->create()->id,
        'discount_value' => 24.01,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

test('promotion allocation rejects non positive quantity', function (): void {
    $promotion = Promotion::query()->create([
        'name' => 'Flash test',
        'type' => 'flash_sale',
        'start_at' => now()->addDay(),
        'end_at' => now()->addDays(2),
        'status' => 'scheduled',
    ]);

    $item = PromotionItem::query()->create([
        'promotion_id' => $promotion->id,
        'book_id' => Book::factory()->create()->id,
        'discount_value' => 10,
    ]);

    $account = Account::factory()->create();

    expect(fn () => PromotionAllocation::query()->create([
        'promotion_item_id' => $item->id,
        'account_id' => $account->id,
        'quantity' => 0,
        'status' => PromotionAllocationStatus::RESERVED,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

test('promotions default status is scheduled when omitted on insert', function (): void {
    $id = DB::table('promotions')->insertGetId([
        'name' => 'Default status',
        'type' => 'flash_sale',
        'start_at' => now()->addDay(),
        'end_at' => now()->addDays(2),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('promotions')->where('id', $id)->value('status'))->toBe('scheduled');
});
