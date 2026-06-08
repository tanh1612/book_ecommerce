<?php

use App\Models\Account;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutMiddleware(VerifyCsrfToken::class);
    $this->withHeader('Origin', 'http://localhost');
    Cache::flush();
});

test('guest cannot change password', function (): void {
    $this->patchJson('/api/v1/account/password', [
        'current_password' => 'password',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertUnauthorized();
});

test('authenticated user can change password', function (): void {
    $account = Account::factory()->create([
        'email' => 'member@example.com',
    ]);

    $this->actingAs($account, 'web');

    $this->patchJson('/api/v1/account/password', [
        'current_password' => 'password',
        'password' => 'new-secure-password-99',
        'password_confirmation' => 'new-secure-password-99',
    ])
        ->assertOk()
        ->assertJsonPath('message', 'Mật khẩu đã được đổi thành công.');

    $account->refresh();

    expect(Hash::check('new-secure-password-99', $account->password))->toBeTrue()
        ->and(Hash::check('password', $account->password))->toBeFalse();

    $this->postJson('/api/v1/auth/login', [
        'email' => 'member@example.com',
        'password' => 'new-secure-password-99',
    ])->assertOk();

    $this->postJson('/api/v1/auth/login', [
        'email' => 'member@example.com',
        'password' => 'password',
    ])->assertUnprocessable();
});

test('change password rejects wrong current password', function (): void {
    $account = Account::factory()->create([
        'email' => 'member@example.com',
    ]);

    $this->actingAs($account, 'web');

    $this->patchJson('/api/v1/account/password', [
        'current_password' => 'wrong-password',
        'password' => 'new-secure-password-99',
        'password_confirmation' => 'new-secure-password-99',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('current_password')
        ->assertJsonFragment(['Mật khẩu hiện tại không đúng.']);

    $account->refresh();

    expect(Hash::check('password', $account->password))->toBeTrue();
});

test('change password rejects when new password equals current', function (): void {
    $account = Account::factory()->create([
        'email' => 'member@example.com',
    ]);

    $this->actingAs($account, 'web');

    $this->patchJson('/api/v1/account/password', [
        'current_password' => 'password',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password')
        ->assertJsonFragment(['Mật khẩu mới không được trùng với mật khẩu hiện tại.']);

    $account->refresh();

    expect(Hash::check('password', $account->password))->toBeTrue();
});

test('change password validates password confirmation', function (): void {
    $account = Account::factory()->create([
        'email' => 'member@example.com',
    ]);

    $this->actingAs($account, 'web');

    $this->patchJson('/api/v1/account/password', [
        'current_password' => 'password',
        'password' => 'new-secure-password-99',
        'password_confirmation' => 'different',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');
});

test('change password validates required fields', function (): void {
    $account = Account::factory()->create([
        'email' => 'member@example.com',
    ]);

    $this->actingAs($account, 'web');

    $this->patchJson('/api/v1/account/password', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['current_password', 'password']);
});
