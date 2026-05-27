<?php

use App\Enums\Account\AccountRole;
use App\Enums\Promotion\PromotionStatus;
use App\Enums\Promotion\PromotionType;
use App\Filament\Resources\PromotionResource\Pages\CreatePromotion;
use App\Filament\Resources\PromotionResource\Pages\EditPromotion;
use App\Filament\Resources\PromotionResource\Pages\ListPromotions;
use App\Models\Account;
use App\Models\Book;
use App\Models\Promotion;
use App\Models\PromotionItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * @return array<string, mixed>
 */
function validPromotionFormPayload(Book $book): array
{
    return [
        'name' => 'Chiến dịch test',
        'start_at' => now()->addDay()->format('Y-m-d H:i:s'),
        'end_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
        'items' => [
            [
                'book_id' => $book->id,
                'discount_value' => 10,
            ],
        ],
    ];
}

test('creating promotion always stores scheduled status', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);
    $book = Book::factory()->create();

    Livewire::actingAs($admin)
        ->test(CreatePromotion::class)
        ->fillForm(validPromotionFormPayload($book))
        ->call('create')
        ->assertHasNoFormErrors();

    $promotion = Promotion::query()->firstOrFail();

    expect($promotion->status)->toBe(PromotionStatus::SCHEDULED)
        ->and($promotion->items)->toHaveCount(1)
        ->and($promotion->items->first()->book_id)->toBe($book->id)
        ->and($promotion->items->first()->discount_value)->toBe(10);
});

test('creating promotion persists multiple items in one request', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);
    $firstBook = Book::factory()->create();
    $secondBook = Book::factory()->create();

    Livewire::actingAs($admin)
        ->test(CreatePromotion::class)
        ->fillForm([
            'name' => 'Multi item flash',
            'start_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'end_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'items' => [
                [
                    'book_id' => $firstBook->id,
                    'discount_value' => 10,
                ],
                [
                    'book_id' => $secondBook->id,
                    'discount_value' => 20,
                    'stock_limit' => 5,
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $promotion = Promotion::query()->with('items')->firstOrFail();

    expect($promotion->items)->toHaveCount(2)
        ->and($promotion->items->pluck('book_id')->sort()->values()->all())
        ->toBe(collect([$firstBook->id, $secondBook->id])->sort()->values()->all());
});

test('creating promotion rejects empty items list', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);

    Livewire::actingAs($admin)
        ->test(CreatePromotion::class)
        ->fillForm([
            'name' => 'No items',
            'start_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'end_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'items' => [],
        ])
        ->call('create')
        ->assertHasFormErrors(['items']);

    expect(Promotion::query()->count())->toBe(0)
        ->and(PromotionItem::query()->count())->toBe(0);
});

test('creating promotion rejects duplicate books in items payload', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);
    $book = Book::factory()->create();

    Livewire::actingAs($admin)
        ->test(CreatePromotion::class)
        ->fillForm([
            'name' => 'Duplicate books',
            'start_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'end_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'items' => [
                [
                    'book_id' => $book->id,
                    'discount_value' => 10,
                ],
                [
                    'book_id' => $book->id,
                    'discount_value' => 15,
                ],
            ],
        ])
        ->call('create');

    expect(Promotion::query()->count())->toBe(0)
        ->and(PromotionItem::query()->count())->toBe(0);
});

test('creating promotion rejects book already in another flash sale window', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);
    $book = Book::factory()->create();

    Promotion::query()->create([
        'name' => 'Existing flash',
        'type' => PromotionType::FLASH_SALE,
        'start_at' => now()->addDay(),
        'end_at' => now()->addDays(3),
        'status' => PromotionStatus::SCHEDULED,
    ])->items()->create([
        'book_id' => $book->id,
        'discount_value' => 10,
    ]);

    Livewire::actingAs($admin)
        ->test(CreatePromotion::class)
        ->fillForm(validPromotionFormPayload($book))
        ->call('create');

    expect(Promotion::query()->count())->toBe(1)
        ->and(PromotionItem::query()->count())->toBe(1);
});

test('creating promotion ignores client supplied status payload', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);
    $book = Book::factory()->create();

    Livewire::actingAs($admin)
        ->test(CreatePromotion::class)
        ->fillForm(validPromotionFormPayload($book))
        ->set('data.status', PromotionStatus::ACTIVE->value)
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Promotion::query()->firstOrFail()->status)->toBe(PromotionStatus::SCHEDULED);
});

test('creating promotion always stores flash sale type', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);
    $book = Book::factory()->create();

    Livewire::actingAs($admin)
        ->test(CreatePromotion::class)
        ->fillForm(validPromotionFormPayload($book))
        ->set('data.type', 'discount')
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Promotion::query()->firstOrFail()->type)->toBe(PromotionType::FLASH_SALE);
});

test('creating promotion rejects start_at in the past', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);
    $book = Book::factory()->create();

    $payload = validPromotionFormPayload($book);
    $payload['start_at'] = now()->subHour()->format('Y-m-d H:i:s');
    $payload['end_at'] = now()->addDay()->format('Y-m-d H:i:s');

    Livewire::actingAs($admin)
        ->test(CreatePromotion::class)
        ->fillForm($payload)
        ->call('create')
        ->assertHasFormErrors(['start_at']);

    expect(Promotion::query()->count())->toBe(0);
});

test('creating promotion rejects end_at before or equal to start_at', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);
    $book = Book::factory()->create();

    $payload = validPromotionFormPayload($book);
    $payload['start_at'] = now()->addDays(2)->format('Y-m-d H:i:s');
    $payload['end_at'] = now()->addDay()->format('Y-m-d H:i:s');

    Livewire::actingAs($admin)
        ->test(CreatePromotion::class)
        ->fillForm($payload)
        ->call('create')
        ->assertHasFormErrors(['end_at']);

    expect(Promotion::query()->count())->toBe(0);
});

test('creating promotion rejects end_at equal to start_at', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);
    $book = Book::factory()->create();

    $payload = validPromotionFormPayload($book);
    $startAt = now()->addDays(2)->format('Y-m-d H:i:s');
    $payload['start_at'] = $startAt;
    $payload['end_at'] = $startAt;

    Livewire::actingAs($admin)
        ->test(CreatePromotion::class)
        ->fillForm($payload)
        ->call('create')
        ->assertHasFormErrors(['end_at']);

    expect(Promotion::query()->count())->toBe(0);
});

test('non scheduled promotions hide edit action on list', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);

    $scheduled = Promotion::query()->create([
        'name' => 'Scheduled',
        'type' => PromotionType::FLASH_SALE,
        'start_at' => now()->addDay(),
        'end_at' => now()->addDays(2),
        'status' => PromotionStatus::SCHEDULED,
    ]);

    $active = Promotion::query()->create([
        'name' => 'Active',
        'type' => PromotionType::FLASH_SALE,
        'start_at' => now()->subDay(),
        'end_at' => now()->addDay(),
        'status' => PromotionStatus::ACTIVE,
    ]);

    Livewire::actingAs($admin)
        ->test(ListPromotions::class)
        ->assertTableActionVisible('edit', $scheduled)
        ->assertTableActionHidden('edit', $active);
});

test('promotion list tabs count and scope promotions by status', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);

    $promotions = collect(PromotionStatus::cases())->mapWithKeys(
        fn (PromotionStatus $status): array => [
            $status->value => Promotion::query()->create([
                'name' => $status->value,
                'type' => PromotionType::FLASH_SALE,
                'start_at' => now()->addDay(),
                'end_at' => now()->addDays(2),
                'status' => $status,
            ]),
        ],
    );

    $tabs = app(ListPromotions::class)->getTabs();

    expect($tabs['all']->getLabel())->toBe('Tất cả')
        ->and($tabs['all']->getBadge())->toBe(count(PromotionStatus::cases()))
        ->and($tabs['all']->getBadgeColor())->toBe('primary');

    foreach (PromotionStatus::cases() as $status) {
        expect($tabs[$status->value]->getLabel())->toBe($status->getLabel())
            ->and($tabs[$status->value]->getBadge())->toBe(1)
            ->and($tabs[$status->value]->getBadgeColor())->toBe($status->getColor())
            ->and($tabs[$status->value]->isBadgeDeferred())->toBeTrue();
    }

    Livewire::actingAs($admin)
        ->test(ListPromotions::class)
        ->set('activeTab', PromotionStatus::ACTIVE->value)
        ->assertCanSeeTableRecords([$promotions[PromotionStatus::ACTIVE->value]])
        ->assertCanNotSeeTableRecords($promotions->except(PromotionStatus::ACTIVE->value));
});

test('non scheduled promotion cannot open edit page', function (PromotionStatus $status): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);

    $promotion = Promotion::query()->create([
        'name' => 'Locked promotion',
        'type' => PromotionType::FLASH_SALE,
        'start_at' => now()->subDays(2),
        'end_at' => now()->addDay(),
        'status' => $status,
    ]);

    Livewire::actingAs($admin)
        ->test(EditPromotion::class, ['record' => $promotion->getKey()])
        ->assertForbidden();
})->with([
    'active' => PromotionStatus::ACTIVE,
    'expired' => PromotionStatus::EXPIRED,
    'cancelled' => PromotionStatus::CANCELLED,
]);

test('scheduled promotion can be edited with future start_at', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);
    $book = Book::factory()->create();

    $promotion = Promotion::query()->create([
        'name' => 'Editable',
        'type' => PromotionType::FLASH_SALE,
        'start_at' => now()->addDays(3),
        'end_at' => now()->addDays(5),
        'status' => PromotionStatus::SCHEDULED,
    ]);

    $promotion->items()->create([
        'book_id' => $book->id,
        'discount_value' => 10,
    ]);

    Livewire::actingAs($admin)
        ->test(EditPromotion::class, ['record' => $promotion->getKey()])
        ->fillForm([
            'name' => 'Editable updated',
            'start_at' => now()->addDays(4)->format('Y-m-d H:i:s'),
            'end_at' => now()->addDays(6)->format('Y-m-d H:i:s'),
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $promotion->refresh();

    expect($promotion->name)->toBe('Editable updated')
        ->and($promotion->status)->toBe(PromotionStatus::SCHEDULED);
});

test('editing scheduled promotion rejects start_at in the past', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);

    $promotion = Promotion::query()->create([
        'name' => 'Editable',
        'type' => PromotionType::FLASH_SALE,
        'start_at' => now()->addDays(3),
        'end_at' => now()->addDays(5),
        'status' => PromotionStatus::SCHEDULED,
    ]);

    Livewire::actingAs($admin)
        ->test(EditPromotion::class, ['record' => $promotion->getKey()])
        ->fillForm([
            'name' => 'Editable',
            'start_at' => now()->subHour()->format('Y-m-d H:i:s'),
            'end_at' => now()->addDays(5)->format('Y-m-d H:i:s'),
        ])
        ->call('save')
        ->assertHasFormErrors(['start_at']);
});

test('edit save aborts when promotion status changed while form is open', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);
    $book = Book::factory()->create();

    $promotion = Promotion::query()->create([
        'name' => 'Editable',
        'type' => PromotionType::FLASH_SALE,
        'start_at' => now()->addDays(3),
        'end_at' => now()->addDays(5),
        'status' => PromotionStatus::SCHEDULED,
    ]);

    $promotion->items()->create([
        'book_id' => $book->id,
        'discount_value' => 10,
    ]);

    $component = Livewire::actingAs($admin)
        ->test(EditPromotion::class, ['record' => $promotion->getKey()]);

    $promotion->update(['status' => PromotionStatus::ACTIVE]);

    $component
        ->fillForm([
            'name' => 'Should not save',
            'start_at' => now()->addDays(4)->format('Y-m-d H:i:s'),
            'end_at' => now()->addDays(6)->format('Y-m-d H:i:s'),
        ])
        ->call('save')
        ->assertForbidden();

    $promotion->refresh();

    expect($promotion->name)->toBe('Editable')
        ->and($promotion->status)->toBe(PromotionStatus::ACTIVE);
});

test('scheduled promotion can be cancelled from edit page', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);

    $promotion = Promotion::query()->create([
        'name' => 'To cancel',
        'type' => PromotionType::FLASH_SALE,
        'start_at' => now()->addDay(),
        'end_at' => now()->addDays(2),
        'status' => PromotionStatus::SCHEDULED,
    ]);

    Livewire::actingAs($admin)
        ->test(EditPromotion::class, ['record' => $promotion->getKey()])
        ->callAction('cancel')
        ->assertHasNoActionErrors();

    expect($promotion->fresh()->status)->toBe(PromotionStatus::CANCELLED);
});

test('unused scheduled promotion can be deleted', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);

    $promotion = Promotion::query()->create([
        'name' => 'To delete',
        'type' => PromotionType::FLASH_SALE,
        'start_at' => now()->addDay(),
        'end_at' => now()->addDays(2),
        'status' => PromotionStatus::SCHEDULED,
    ]);

    Livewire::actingAs($admin)
        ->test(EditPromotion::class, ['record' => $promotion->getKey()])
        ->callAction('delete')
        ->assertHasNoActionErrors();

    expect(Promotion::query()->whereKey($promotion->id)->exists())->toBeFalse();
});

test('editing scheduled flash sale rejects date range overlapping another campaign', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);
    $book = Book::factory()->create();

    Promotion::query()->create([
        'name' => 'Other flash',
        'type' => PromotionType::FLASH_SALE,
        'start_at' => now()->addDays(5),
        'end_at' => now()->addDays(8),
        'status' => PromotionStatus::SCHEDULED,
    ])->items()->create([
        'book_id' => $book->id,
        'discount_value' => 10,
    ]);

    $promotion = Promotion::query()->create([
        'name' => 'Editable flash',
        'type' => PromotionType::FLASH_SALE,
        'start_at' => now()->addDays(10),
        'end_at' => now()->addDays(12),
        'status' => PromotionStatus::SCHEDULED,
    ]);

    $promotion->items()->create([
        'book_id' => $book->id,
        'discount_value' => 15,
    ]);

    Livewire::actingAs($admin)
        ->test(EditPromotion::class, ['record' => $promotion->getKey()])
        ->fillForm([
            'name' => 'Editable flash',
            'start_at' => now()->addDays(6)->format('Y-m-d H:i:s'),
            'end_at' => now()->addDays(9)->format('Y-m-d H:i:s'),
        ])
        ->call('save');

    $promotion->refresh();

    expect($promotion->start_at->format('Y-m-d'))->toBe(now()->addDays(10)->format('Y-m-d'))
        ->and($promotion->end_at->format('Y-m-d'))->toBe(now()->addDays(12)->format('Y-m-d'));
});

test('active promotion hides delete action on list table', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);

    $promotion = Promotion::query()->create([
        'name' => 'Active promo',
        'type' => PromotionType::FLASH_SALE,
        'start_at' => now()->subMinute(),
        'end_at' => now()->addHour(),
        'status' => PromotionStatus::ACTIVE,
    ]);

    Livewire::actingAs($admin)
        ->test(ListPromotions::class)
        ->assertTableActionHidden('delete', $promotion);

    expect(Promotion::query()->whereKey($promotion->id)->exists())->toBeTrue();
});
