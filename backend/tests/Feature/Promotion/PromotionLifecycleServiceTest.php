<?php

use App\Enums\Promotion\PromotionStatus;
use App\Models\Book;
use App\Models\OrderItem;
use App\Models\Promotion;
use App\Models\PromotionAllocation;
use App\Models\PromotionItem;
use App\Enums\Order\OrderStatus;
use App\Enums\Promotion\PromotionAllocationStatus;
use App\Enums\Order\PaymentMethod;
use App\Enums\Order\PaymentStatus;
use App\Services\Promotion\PromotionLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('lifecycle service sync activates and expires by schedule', function (): void {
    $scheduledFuture = Promotion::query()->create([
        'name' => 'Future',
        'type' => 'flash_sale',
        'start_at' => now()->addHour(),
        'end_at' => now()->addDays(2),
        'status' => PromotionStatus::SCHEDULED,
    ]);

    $toActivate = Promotion::query()->create([
        'name' => 'Starts now',
        'type' => 'flash_sale',
        'start_at' => now()->subMinute(),
        'end_at' => now()->addHour(),
        'status' => PromotionStatus::SCHEDULED,
    ]);

    $toExpire = Promotion::query()->create([
        'name' => 'Ended',
        'type' => 'flash_sale',
        'start_at' => now()->subHours(2),
        'end_at' => now()->subMinute(),
        'status' => PromotionStatus::ACTIVE,
    ]);

    $cancelled = Promotion::query()->create([
        'name' => 'Cancelled',
        'type' => 'flash_sale',
        'start_at' => now()->subHour(),
        'end_at' => now()->addHour(),
        'status' => PromotionStatus::CANCELLED,
    ]);

    app(PromotionLifecycleService::class)->syncStatuses();

    expect($scheduledFuture->fresh()->status)->toBe(PromotionStatus::SCHEDULED)
        ->and($toActivate->fresh()->status)->toBe(PromotionStatus::ACTIVE)
        ->and($toExpire->fresh()->status)->toBe(PromotionStatus::EXPIRED)
        ->and($cancelled->fresh()->status)->toBe(PromotionStatus::CANCELLED);
});

test('lifecycle service blocks delete when promotion was used on order', function (): void {
    $promotion = Promotion::query()->create([
        'name' => 'Used',
        'type' => 'flash_sale',
        'start_at' => now()->addDay(),
        'end_at' => now()->addDays(2),
        'status' => PromotionStatus::SCHEDULED,
    ]);

    $orderId = \App\Models\Order::query()->create([
        'account_id' => \App\Models\Account::factory()->create()->id,
        'shipping_method_id' => \App\Models\ShippingMethod::query()->create([
            'name' => 'Std',
            'description' => null,
            'is_active' => true,
        ])->id,
        'total_amount' => 100000,
        'shipping_fee' => 0,
        'final_amount' => 100000,
        'shipping_name' => 'A',
        'shipping_phone' => '0900000000',
        'shipping_address' => 'Addr',
        'payment_method' => PaymentMethod::COD,
        'payment_status' => PaymentStatus::PENDING,
        'current_status' => OrderStatus::CONFIRMED,
    ])->id;

    OrderItem::query()->create([
        'order_id' => $orderId,
        'book_id' => Book::factory()->create()->id,
        'promotion_id' => $promotion->id,
        'price' => 100000,
        'quantity' => 1,
        'total_price' => 100000,
        'discount_amount' => 0,
        'is_reviewed' => false,
    ]);

    expect(fn () => app(PromotionLifecycleService::class)->assertDeletable($promotion))
        ->toThrow(ValidationException::class);
});

test('lifecycle service cancel only allows scheduled promotions', function (): void {
    $active = Promotion::query()->create([
        'name' => 'Active',
        'type' => 'flash_sale',
        'start_at' => now()->subMinute(),
        'end_at' => now()->addHour(),
        'status' => PromotionStatus::ACTIVE,
    ]);

    expect(fn () => app(PromotionLifecycleService::class)->cancel($active))
        ->toThrow(ValidationException::class);
});

test('lifecycle service blocks mutations after a scheduled campaign has started before status sync', function (): void {
    $started = Promotion::query()->create([
        'name' => 'Started but not synced',
        'type' => 'flash_sale',
        'start_at' => now()->subMinute(),
        'end_at' => now()->addHour(),
        'status' => PromotionStatus::SCHEDULED,
    ]);

    $lifecycle = app(PromotionLifecycleService::class);

    expect($lifecycle->canEdit($started))->toBeFalse()
        ->and($lifecycle->canCancel($started))->toBeFalse()
        ->and(fn () => $lifecycle->cancel($started))->toThrow(ValidationException::class)
        ->and(fn () => $lifecycle->deleteScheduledPromotion($started))->toThrow(ValidationException::class)
        ->and(fn () => $lifecycle->updateScheduledPromotion($started, fn (): null => null))->toThrow(ValidationException::class);
});

test('lifecycle service blocks delete promotion item linked on order line', function (): void {
    $promotion = Promotion::query()->create([
        'name' => 'Scheduled with used item',
        'type' => 'flash_sale',
        'start_at' => now()->addDay(),
        'end_at' => now()->addDays(2),
        'status' => PromotionStatus::SCHEDULED,
    ]);

    $item = PromotionItem::query()->create([
        'promotion_id' => $promotion->id,
        'book_id' => Book::factory()->create()->id,
        'discount_value' => 10,
    ]);

    $orderId = \App\Models\Order::query()->create([
        'account_id' => \App\Models\Account::factory()->create()->id,
        'shipping_method_id' => \App\Models\ShippingMethod::query()->create([
            'name' => 'Std',
            'description' => null,
            'is_active' => true,
        ])->id,
        'total_amount' => 100000,
        'shipping_fee' => 0,
        'final_amount' => 100000,
        'shipping_name' => 'A',
        'shipping_phone' => '0900000000',
        'shipping_address' => 'Addr',
        'payment_method' => PaymentMethod::COD,
        'payment_status' => PaymentStatus::PENDING,
        'current_status' => OrderStatus::CONFIRMED,
    ])->id;

    OrderItem::query()->create([
        'order_id' => $orderId,
        'book_id' => $item->book_id,
        'promotion_id' => $promotion->id,
        'promotion_item_id' => $item->id,
        'price' => 100000,
        'quantity' => 1,
        'total_price' => 100000,
        'discount_amount' => 0,
        'is_reviewed' => false,
    ]);

    expect(fn () => app(PromotionLifecycleService::class)->deleteScheduledPromotionItem($promotion, $item))
        ->toThrow(ValidationException::class);

    expect(PromotionItem::query()->whereKey($item->id)->exists())->toBeTrue();
});

test('lifecycle service blocks delete promotion item with allocations', function (): void {
    $promotion = Promotion::query()->create([
        'name' => 'Scheduled with allocation',
        'type' => 'flash_sale',
        'start_at' => now()->addDay(),
        'end_at' => now()->addDays(2),
        'status' => PromotionStatus::SCHEDULED,
    ]);

    $item = PromotionItem::query()->create([
        'promotion_id' => $promotion->id,
        'book_id' => Book::factory()->create()->id,
        'discount_value' => 10,
    ]);

    PromotionAllocation::query()->create([
        'promotion_item_id' => $item->id,
        'account_id' => \App\Models\Account::factory()->create()->id,
        'order_id' => null,
        'order_item_id' => null,
        'quantity' => 1,
        'status' => PromotionAllocationStatus::RESERVED,
    ]);

    expect(fn () => app(PromotionLifecycleService::class)->deleteScheduledPromotionItem($promotion, $item))
        ->toThrow(ValidationException::class);

    expect(PromotionItem::query()->whereKey($item->id)->exists())->toBeTrue();
});

test('lifecycle service deletes unused scheduled promotion item', function (): void {
    $promotion = Promotion::query()->create([
        'name' => 'Scheduled unused item',
        'type' => 'flash_sale',
        'start_at' => now()->addDay(),
        'end_at' => now()->addDays(2),
        'status' => PromotionStatus::SCHEDULED,
    ]);

    $item = PromotionItem::query()->create([
        'promotion_id' => $promotion->id,
        'book_id' => Book::factory()->create()->id,
        'discount_value' => 10,
    ]);

    app(PromotionLifecycleService::class)->deleteScheduledPromotionItem($promotion, $item);

    expect(PromotionItem::query()->whereKey($item->id)->exists())->toBeFalse();
});

test('lifecycle service bulk delete rolls back when any item was used', function (): void {
    $promotion = Promotion::query()->create([
        'name' => 'Bulk delete guard',
        'type' => 'flash_sale',
        'start_at' => now()->addDay(),
        'end_at' => now()->addDays(2),
        'status' => PromotionStatus::SCHEDULED,
    ]);

    $unused = PromotionItem::query()->create([
        'promotion_id' => $promotion->id,
        'book_id' => Book::factory()->create()->id,
        'discount_value' => 10,
    ]);

    $used = PromotionItem::query()->create([
        'promotion_id' => $promotion->id,
        'book_id' => Book::factory()->create()->id,
        'discount_value' => 15,
    ]);

    OrderItem::query()->create([
        'order_id' => \App\Models\Order::query()->create([
            'account_id' => \App\Models\Account::factory()->create()->id,
            'shipping_method_id' => \App\Models\ShippingMethod::query()->create([
                'name' => 'Std',
                'description' => null,
                'is_active' => true,
            ])->id,
            'total_amount' => 100000,
            'shipping_fee' => 0,
            'final_amount' => 100000,
            'shipping_name' => 'A',
            'shipping_phone' => '0900000000',
            'shipping_address' => 'Addr',
            'payment_method' => PaymentMethod::COD,
            'payment_status' => PaymentStatus::PENDING,
            'current_status' => OrderStatus::CONFIRMED,
        ])->id,
        'book_id' => $used->book_id,
        'promotion_id' => $promotion->id,
        'promotion_item_id' => $used->id,
        'price' => 100000,
        'quantity' => 1,
        'total_price' => 100000,
        'discount_amount' => 0,
        'is_reviewed' => false,
    ]);

    expect(fn () => app(PromotionLifecycleService::class)->deleteScheduledPromotionItems($promotion, [$unused, $used]))
        ->toThrow(ValidationException::class);

    expect(PromotionItem::query()->whereKey($unused->id)->exists())->toBeTrue()
        ->and(PromotionItem::query()->whereKey($used->id)->exists())->toBeTrue();
});

test('lifecycle service bulk delete rolls back all promotions when any is not deletable', function (): void {
    $deletable = Promotion::query()->create([
        'name' => 'Scheduled deletable',
        'type' => 'flash_sale',
        'start_at' => now()->addDay(),
        'end_at' => now()->addDays(2),
        'status' => PromotionStatus::SCHEDULED,
    ]);

    $active = Promotion::query()->create([
        'name' => 'Active blocked',
        'type' => 'flash_sale',
        'start_at' => now()->subMinute(),
        'end_at' => now()->addHour(),
        'status' => PromotionStatus::ACTIVE,
    ]);

    expect(fn () => app(PromotionLifecycleService::class)->deleteScheduledPromotions([$deletable, $active]))
        ->toThrow(ValidationException::class);

    expect(Promotion::query()->whereKey($deletable->id)->exists())->toBeTrue()
        ->and(Promotion::query()->whereKey($active->id)->exists())->toBeTrue();
});

test('lifecycle service bulk delete removes all scheduled unused promotions', function (): void {
    $first = Promotion::query()->create([
        'name' => 'First',
        'type' => 'flash_sale',
        'start_at' => now()->addDay(),
        'end_at' => now()->addDays(2),
        'status' => PromotionStatus::SCHEDULED,
    ]);

    $second = Promotion::query()->create([
        'name' => 'Second',
        'type' => 'flash_sale',
        'start_at' => now()->addDays(3),
        'end_at' => now()->addDays(5),
        'status' => PromotionStatus::SCHEDULED,
    ]);

    app(PromotionLifecycleService::class)->deleteScheduledPromotions([$first, $second]);

    expect(Promotion::query()->whereKey([$first->id, $second->id])->exists())->toBeFalse();
});

test('lifecycle service cancel keeps promotion items', function (): void {
    $promotion = Promotion::query()->create([
        'name' => 'Cancel me',
        'type' => 'flash_sale',
        'start_at' => now()->addDay(),
        'end_at' => now()->addDays(2),
        'status' => PromotionStatus::SCHEDULED,
    ]);

    PromotionItem::query()->create([
        'promotion_id' => $promotion->id,
        'book_id' => \App\Models\Book::factory()->create()->id,
        'discount_value' => 10,
    ]);

    app(PromotionLifecycleService::class)->cancel($promotion);

    expect($promotion->fresh()->status)->toBe(PromotionStatus::CANCELLED)
        ->and($promotion->items()->count())->toBe(1);
});
