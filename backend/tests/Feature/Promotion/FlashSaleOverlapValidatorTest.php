<?php

use App\Enums\Promotion\PromotionStatus;
use App\Models\Book;
use App\Models\Promotion;
use App\Services\Promotion\FlashSaleOverlapValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('flash sale overlap validator rejects overlapping book windows', function (): void {
    $book = Book::factory()->create();

    $existing = Promotion::query()->create([
        'name' => 'Existing flash',
        'type' => 'flash_sale',
        'start_at' => now()->addDays(2),
        'end_at' => now()->addDays(5),
        'status' => PromotionStatus::SCHEDULED,
    ]);

    $existing->items()->create([
        'book_id' => $book->id,
        'discount_value' => 10,
    ]);

    expect(fn () => app(FlashSaleOverlapValidator::class)->assertBookAvailableForFlashSaleWindow(
        (int) $book->id,
        now()->addDays(3),
        now()->addDays(6),
    ))->toThrow(ValidationException::class);
});

test('flash sale overlap validator rejects overlapping campaign windows', function (): void {
    Promotion::query()->create([
        'name' => 'Existing campaign',
        'type' => 'flash_sale',
        'start_at' => now()->addDays(2),
        'end_at' => now()->addDays(5),
        'status' => PromotionStatus::SCHEDULED,
    ]);

    expect(fn () => app(FlashSaleOverlapValidator::class)->assertNoOverlappingFlashSaleCampaign(
        now()->addDays(3),
        now()->addDays(6),
    ))->toThrow(ValidationException::class);
});

test('flash sale overlap validator allows campaigns with touching boundaries', function (): void {
    $existing = Promotion::query()->create([
        'name' => '13h to 15h',
        'type' => 'flash_sale',
        'start_at' => now()->addHours(1),
        'end_at' => now()->addHours(3),
        'status' => PromotionStatus::SCHEDULED,
    ]);

    app(FlashSaleOverlapValidator::class)->assertNoOverlappingFlashSaleCampaign(
        $existing->end_at,
        $existing->end_at->copy()->addHours(2),
    );

    expect(true)->toBeTrue();
});

test('flash sale overlap validator allows same promotion edit window', function (): void {
    $book = Book::factory()->create();

    $promotion = Promotion::query()->create([
        'name' => 'Editable flash',
        'type' => 'flash_sale',
        'start_at' => now()->addDays(2),
        'end_at' => now()->addDays(5),
        'status' => PromotionStatus::SCHEDULED,
    ]);

    app(FlashSaleOverlapValidator::class)->assertBookAvailableForFlashSaleWindow(
        (int) $book->id,
        $promotion->start_at,
        $promotion->end_at,
        (int) $promotion->id,
    );

    expect(true)->toBeTrue();
});
