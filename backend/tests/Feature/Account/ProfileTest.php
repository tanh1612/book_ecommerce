<?php

use App\Enums\Account\AccountRole;
use App\Enums\Account\UserGender;
use App\Models\Account;
use App\Models\UserProfile;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutMiddleware(VerifyCsrfToken::class);
    Cache::flush();
});

test('guest cannot get profile', function (): void {
    $this->getJson('/api/v1/account/profile')->assertUnauthorized();
});

test('guest cannot update profile', function (): void {
    $this->patchJson('/api/v1/account/profile', [
        'first_name' => 'A',
    ])->assertUnauthorized();
});

test('authenticated user can get profile', function (): void {
    $account = Account::factory()->create([
        'email' => 'member@example.com',
    ]);

    UserProfile::query()->create([
        'account_id' => $account->id,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'phone' => '0909123456',
        'gender' => UserGender::Female,
        'birthday' => '1990-05-10',
    ]);

    $this->actingAs($account, 'web');

    $this->getJson('/api/v1/account/profile')
        ->assertOk()
        ->assertJsonPath('data.email', 'member@example.com')
        ->assertJsonPath('data.profile.first_name', 'Jane')
        ->assertJsonPath('data.profile.last_name', 'Doe')
        ->assertJsonPath('data.profile.phone', '0909123456')
        ->assertJsonPath('data.profile.gender', 'female')
        ->assertJsonPath('data.profile.birthday', '1990-05-10');
});

test('authenticated user can update profile and persist to user_profiles', function (): void {
    $account = Account::factory()->create([
        'email' => 'member@example.com',
    ]);

    UserProfile::query()->create([
        'account_id' => $account->id,
        'first_name' => null,
        'last_name' => null,
        'phone' => null,
        'gender' => null,
        'birthday' => null,
    ]);

    $this->actingAs($account, 'web');

    $this->patchJson('/api/v1/account/profile', [
        'first_name' => 'John',
        'last_name' => 'Smith',
        'phone' => '0987654321',
        'gender' => 'male',
        'birthday' => '1995-01-15',
    ])
        ->assertOk()
        ->assertJsonPath('data.profile.first_name', 'John')
        ->assertJsonPath('data.profile.last_name', 'Smith')
        ->assertJsonPath('data.profile.phone', '0987654321')
        ->assertJsonPath('data.profile.gender', 'male')
        ->assertJsonPath('data.profile.birthday', '1995-01-15');

    $this->assertDatabaseHas('user_profiles', [
        'account_id' => $account->id,
        'first_name' => 'John',
        'last_name' => 'Smith',
        'phone' => '0987654321',
        'gender' => 'male',
    ]);

    expect(UserProfile::query()->where('account_id', $account->id)->first()?->birthday?->format('Y-m-d'))
        ->toBe('1995-01-15');
});

test('update creates user_profiles when row is missing', function (): void {
    $account = Account::factory()->create([
        'email' => 'noprofile@example.com',
    ]);

    expect(UserProfile::query()->where('account_id', $account->id)->exists())->toBeFalse();

    $this->actingAs($account, 'web');

    $this->patchJson('/api/v1/account/profile', [
        'first_name' => 'Solo',
    ])
        ->assertOk()
        ->assertJsonPath('data.profile.first_name', 'Solo');

    $this->assertDatabaseHas('user_profiles', [
        'account_id' => $account->id,
        'first_name' => 'Solo',
    ]);
});

test('client cannot change account via prohibited fields', function (): void {
    $account = Account::factory()->create([
        'email' => 'locked@example.com',
        'role' => AccountRole::Customer,
        'is_active' => true,
    ]);

    UserProfile::query()->create([
        'account_id' => $account->id,
        'first_name' => 'X',
    ]);

    $this->actingAs($account, 'web');

    $this->patchJson('/api/v1/account/profile', [
        'first_name' => 'Valid',
        'email' => 'hacker@example.com',
        'role' => AccountRole::Admin->value,
        'is_active' => false,
        'account_id' => 99999,
        'email_verified_at' => '2000-01-01 00:00:00',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'role', 'is_active', 'account_id', 'email_verified_at']);

    $account->refresh();

    expect($account->email)->toBe('locked@example.com')
        ->and($account->role)->toBe(AccountRole::Customer)
        ->and($account->is_active)->toBeTrue();

    $this->assertDatabaseHas('user_profiles', [
        'account_id' => $account->id,
        'first_name' => 'X',
    ]);
});

test('profile update rejects future birthday', function (): void {
    $account = Account::factory()->create();

    UserProfile::query()->create([
        'account_id' => $account->id,
    ]);

    $this->actingAs($account, 'web');

    $this->patchJson('/api/v1/account/profile', [
        'birthday' => now()->addDay()->format('Y-m-d'),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['birthday']);
});

test('profile update rejects fields exceeding max length', function (): void {
    $account = Account::factory()->create();

    UserProfile::query()->create([
        'account_id' => $account->id,
    ]);

    $this->actingAs($account, 'web');

    $this->patchJson('/api/v1/account/profile', [
        'first_name' => str_repeat('a', 101),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['first_name']);
});

test('profile update rejects invalid gender', function (): void {
    $account = Account::factory()->create();

    UserProfile::query()->create([
        'account_id' => $account->id,
    ]);

    $this->actingAs($account, 'web');

    $this->patchJson('/api/v1/account/profile', [
        'gender' => 'other',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['gender']);
});

test('profile update rejects empty payload', function (): void {
    $account = Account::factory()->create();

    UserProfile::query()->create([
        'account_id' => $account->id,
    ]);

    $this->actingAs($account, 'web');

    $this->patchJson('/api/v1/account/profile', [])
        ->assertUnprocessable();
});
