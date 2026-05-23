<?php

use App\Enums\Account\AccountRole;
use App\Filament\Resources\AccountResource\Pages\ListAccounts;
use App\Filament\Resources\AccountResource\Pages\ViewAccount;
use App\Models\Account;
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

test('view account has no delete action', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);
    $target = Account::factory()->create();

    Livewire::actingAs($admin)
        ->test(ViewAccount::class, ['record' => $target->getKey()])
        ->assertActionDoesNotExist('delete');
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
