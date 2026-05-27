<?php

use App\Enums\Promotion\PromotionStatus;
use App\Models\Account;
use App\Models\Book;
use App\Models\Promotion;
use App\Models\PromotionAllocation;
use App\Models\PromotionItem;
use App\Services\Promotion\FlashSaleResolver;
use App\Services\Promotion\PromotionAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('reserve rejects when campaign expired after item was resolved for checkout', function (): void {
    $account = Account::factory()->create();
    $book = checkoutBookWithStock(5);

    $promotion = Promotion::query()->create([
        'name' => 'Reserve guard',
        'type' => 'flash_sale',
        'start_at' => now()->subMinute(),
        'end_at' => now()->addHour(),
        'status' => PromotionStatus::ACTIVE,
    ]);

    $item = PromotionItem::query()->create([
        'promotion_id' => $promotion->id,
        'book_id' => $book->id,
        'discount_value' => 10,
        'stock_limit' => 5,
    ]);

    $resolved = app(FlashSaleResolver::class)->activeItemsForBooks([(int) $book->id])->get((int) $book->id);

    expect($resolved)->not->toBeNull()
        ->and((int) $resolved->id)->toBe((int) $item->id);

    $promotion->update([
        'status' => PromotionStatus::EXPIRED,
        'end_at' => now()->subSecond(),
    ]);

    $orderItem = reserveTestOrderItem($account, $book, $promotion, $item);

    expect(fn () => app(PromotionAllocationService::class)->reserve($account, $item, $orderItem))
        ->toThrow(ValidationException::class);

    expect((int) $item->fresh()->sold_quantity)->toBe(0)
        ->and(PromotionAllocation::query()->count())->toBe(0);
});

test('second reserve call cannot oversell flash sale stock limit', function (): void {
    $accountA = Account::factory()->create();
    $accountB = Account::factory()->create();
    $book = checkoutBookWithStock(10);

    $promotion = Promotion::query()->create([
        'name' => 'Single unit',
        'type' => 'flash_sale',
        'start_at' => now()->subMinute(),
        'end_at' => now()->addHour(),
        'status' => PromotionStatus::ACTIVE,
    ]);

    $item = PromotionItem::query()->create([
        'promotion_id' => $promotion->id,
        'book_id' => $book->id,
        'discount_value' => 10,
        'stock_limit' => 1,
    ]);

    $firstOrderItem = reserveTestOrderItem($accountA, $book, $promotion, $item);
    $secondOrderItem = reserveTestOrderItem($accountB, $book, $promotion, $item);

    app(PromotionAllocationService::class)->reserve($accountA, $item, $firstOrderItem);

    expect(fn () => app(PromotionAllocationService::class)->reserve($accountB, $item, $secondOrderItem))
        ->toThrow(ValidationException::class);

    expect((int) $item->fresh()->sold_quantity)->toBe(1)
        ->and(PromotionAllocation::query()->where('promotion_item_id', $item->id)->count())->toBe(1);
});
