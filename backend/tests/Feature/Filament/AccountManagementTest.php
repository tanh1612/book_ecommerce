<?php

use App\Enums\Account\AccountRole;
use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentMethod;
use App\Enums\Order\PaymentStatus;
use App\Filament\Resources\AccountResource;
use App\Filament\Resources\AccountResource\Pages\ListAccounts;
use App\Filament\Resources\AccountResource\Pages\ViewAccount;
use App\Models\Account;
use App\Models\Order;
use App\Models\ShippingMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('account list exposes only view table action', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);

    Livewire::actingAs($admin)
        ->test(ListAccounts::class)
        ->assertTableActionExists('view')
        ->assertTableActionDoesNotExist('edit')
        ->assertTableActionDoesNotExist('delete');
});

test('account list has no bulk delete', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);

    Livewire::actingAs($admin)
        ->test(ListAccounts::class)
        ->assertTableBulkActionDoesNotExist('delete');
});

test('account list still exposes create header action', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);

    Livewire::actingAs($admin)
        ->test(ListAccounts::class)
        ->assertActionExists('create');
});

test('account list shows all accounts by default and customer tab scopes customer accounts', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);
    $customer = Account::factory()->create(['role' => AccountRole::Customer]);
    $tabs = app(ListAccounts::class)->getTabs();

    expect($tabs['all']->getLabel())->toBe('Tất cả')
        ->and($tabs['all']->getBadge())->toBe(2)
        ->and($tabs['all']->getBadgeColor())->toBe('primary')
        ->and($tabs['customers']->getLabel())->toBe('Khách hàng')
        ->and($tabs['customers']->getBadge())->toBe(1)
        ->and($tabs['customers']->getBadgeColor())->toBe('success')
        ->and($tabs['customers']->isBadgeDeferred())->toBeTrue();

    Livewire::actingAs($admin)
        ->test(ListAccounts::class)
        ->assertCanSeeTableRecords([$admin, $customer])
        ->set('activeTab', 'customers')
        ->assertCanSeeTableRecords([$customer])
        ->assertCanNotSeeTableRecords([$admin]);
});

test('view active account lock action deactivates account', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);
    $target = Account::factory()->create(['is_active' => true]);

    Livewire::actingAs($admin)
        ->test(ViewAccount::class, ['record' => $target->getKey()])
        ->callAction('lock')
        ->assertNotified();

    expect($target->refresh()->is_active)->toBeFalse();
});

test('view inactive account activate action reactivates account', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);
    $target = Account::factory()->create(['is_active' => false]);

    Livewire::actingAs($admin)
        ->test(ViewAccount::class, ['record' => $target->getKey()])
        ->callAction('activate')
        ->assertNotified();

    expect($target->refresh()->is_active)->toBeTrue();
});

test('view active account has no delete action', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);
    $target = Account::factory()->create(['is_active' => true]);

    Livewire::actingAs($admin)
        ->test(ViewAccount::class, ['record' => $target->getKey()])
        ->assertActionHidden('delete');
});

test('view inactive account without unfinished orders can be soft deleted', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);
    $target = Account::factory()->create(['is_active' => false]);

    Livewire::actingAs($admin)
        ->test(ViewAccount::class, ['record' => $target->getKey()])
        ->assertActionVisible('delete')
        ->callAction('delete')
        ->assertNotified()
        ->assertRedirect(AccountResource::getUrl('index'));

    expect(Account::withTrashed()->find($target->id)?->trashed())->toBeTrue();
});

test('view inactive account with unfinished orders cannot be deleted', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);
    $target = Account::factory()->create(['is_active' => false]);

    $shipping = ShippingMethod::query()->create([
        'name' => 'Test ship',
        'description' => null,
        'is_active' => true,
    ]);

    Order::query()->create([
        'account_id' => $target->id,
        'shipping_method_id' => $shipping->id,
        'total_amount' => 100000.00,
        'shipping_fee' => 0,
        'final_amount' => 100000.00,
        'shipping_name' => 'A',
        'shipping_phone' => '0900000000',
        'shipping_address' => 'Addr',
        'payment_method' => PaymentMethod::COD,
        'payment_status' => PaymentStatus::PENDING,
        'note' => null,
        'current_status' => OrderStatus::CONFIRMED,
    ]);

    Livewire::actingAs($admin)
        ->test(ViewAccount::class, ['record' => $target->getKey()])
        ->assertActionDisabled('delete');

    expect(Account::query()->find($target->id))->not->toBeNull();

    expect(fn () => app(\App\Services\Account\AccountDeletionService::class)
        ->softDeleteInactive($target->fresh(), $admin))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('admin cannot delete own inactive account', function (): void {
    $admin = Account::factory()->create([
        'role' => AccountRole::Admin,
        'is_active' => false,
    ]);

    Livewire::actingAs($admin)
        ->test(ViewAccount::class, ['record' => $admin->getKey()])
        ->assertActionHidden('delete');
});

test('inactive admin cannot access filament panel', function (): void {
    $admin = Account::factory()->create([
        'role' => AccountRole::Admin,
        'is_active' => false,
    ]);

    expect($admin->canAccessPanel(filament()->getCurrentOrDefaultPanel()))->toBeFalse();
});

test('admin cannot lock own account and is_active stays unchanged', function (): void {
    $admin = Account::factory()->create([
        'role' => AccountRole::Admin,
        'is_active' => true,
    ]);

    Livewire::actingAs($admin)
        ->test(ViewAccount::class, ['record' => $admin->getKey()])
        ->assertActionHidden('lock');

    expect($admin->refresh()->is_active)->toBeTrue();
});
