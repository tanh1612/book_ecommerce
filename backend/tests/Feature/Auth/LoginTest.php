<?php

use App\Enums\Account\AccountRole;
use App\Models\Account;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutMiddleware(VerifyCsrfToken::class);
    Cache::flush();
});

test('customer can login with valid credentials', function (): void {
    $account = Account::factory()->create([
        'email' => 'member@example.com',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'member@example.com',
        'password' => 'password',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.email', 'member@example.com')
        ->assertJsonPath('data.role', AccountRole::Customer->value);

    $this->assertAuthenticatedAs($account);
});

test('login sets remember cookie when remember is true', function (): void {
    $account = Account::factory()->create([
        'email' => 'remember@example.com',
    ]);
    $recallerName = app('auth')->guard('web')->getRecallerName();

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'remember@example.com',
        'password' => 'password',
        'remember' => true,
    ]);

    $response
        ->assertOk()
        ->assertCookieNotExpired($recallerName);

    $this->assertAuthenticatedAs($account);
});

test('login does not set remember cookie by default', function (): void {
    Account::factory()->create([
        'email' => 'noremember@example.com',
    ]);
    $recallerName = app('auth')->guard('web')->getRecallerName();

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'noremember@example.com',
        'password' => 'password',
    ]);

    $response
        ->assertOk()
        ->assertCookieMissing($recallerName);
});

test('login rejects wrong password', function (): void {
    Account::factory()->create([
        'email' => 'member@example.com',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'member@example.com',
        'password' => 'wrong-password',
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email')
        ->assertJsonFragment(['Email hoặc mật khẩu không đúng.']);

    $this->assertGuest();
});

test('login rejects unknown email like wrong password', function (): void {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'nobody@example.com',
        'password' => 'password',
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    $this->assertGuest();
});

test('login rejects inactive account without counting failed attempts', function (): void {
    Account::factory()->create([
        'email' => 'locked@example.com',
        'is_active' => false,
    ]);

    for ($i = 0; $i < 6; $i++) {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'locked@example.com',
            'password' => 'password',
        ]);

        $response
            ->assertForbidden()
            ->assertJsonFragment(['Tài khoản đã bị khóa hoặc chưa được kích hoạt.']);
    }

    $this->assertGuest();
});

test('login rejects unverified email without counting failed attempts', function (): void {
    Account::factory()->unverified()->create([
        'email' => 'pending@example.com',
    ]);

    for ($i = 0; $i < 6; $i++) {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'pending@example.com',
            'password' => 'password',
        ]);

        $response
            ->assertForbidden()
            ->assertJsonFragment(['Vui lòng xác thực email trước khi đăng nhập.']);
    }

    $this->assertGuest();
});

test('login is throttled after too many wrong passwords', function (): void {
    Account::factory()->create([
        'email' => 'member@example.com',
    ]);

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'member@example.com',
            'password' => 'wrong-password',
        ])->assertUnprocessable();
    }

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'member@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(429);
    expect($response->json('message'))->toContain('Bạn đã nhập sai quá nhiều lần');

    $this->assertGuest();
});

test('successful login clears failed attempt counter', function (): void {
    Account::factory()->create([
        'email' => 'member@example.com',
    ]);

    for ($i = 0; $i < 4; $i++) {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'member@example.com',
            'password' => 'wrong-password',
        ])->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/login', [
        'email' => 'member@example.com',
        'password' => 'password',
    ])->assertOk();

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'member@example.com',
            'password' => 'wrong-password',
        ])->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/login', [
        'email' => 'member@example.com',
        'password' => 'wrong-password',
    ])->assertStatus(429);
});

test('login validates input', function (): void {
    $response = $this->postJson('/api/v1/auth/login', []);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);
});

test('login validates remember type', function (): void {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'member@example.com',
        'password' => 'password',
        'remember' => 'yes',
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['remember']);
});
