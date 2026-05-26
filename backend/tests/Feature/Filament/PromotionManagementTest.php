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
        'type' => PromotionType::FLASH_SALE->value,
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

    expect($promotion->status)->toBe(PromotionStatus::SCHEDULED);
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
        'type' => PromotionType::REGULAR_SALE,
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
                'type' => PromotionType::REGULAR_SALE,
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
            'type' => PromotionType::FLASH_SALE->value,
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
            'type' => PromotionType::FLASH_SALE->value,
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
            'type' => PromotionType::FLASH_SALE->value,
            'start_at' => now()->addDays(4)->format('Y-m-d H:i:s'),
            'end_at' => now()->addDays(6)->format('Y-m-d H:i:s'),
        ])
        ->call('save')
        ->assertForbidden();

    $promotion->refresh();

    expect($promotion->name)->toBe('Editable')
        ->and($promotion->status)->toBe(PromotionStatus::ACTIVE);
});
