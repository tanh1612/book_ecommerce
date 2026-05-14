<?php

use App\Enums\Account\AccountRole;
use App\Mail\RegistrationOtpMail;
use App\Models\Account;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutMiddleware(VerifyCsrfToken::class);
    Cache::flush();
});

test('customer can register an account', function () {
    Mail::fake();

    $email = 'customer@example.com';

    $this->postJson('/api/v1/auth/register/send-otp', ['email' => $email])->assertOk();

    $otp = Mail::queued(RegistrationOtpMail::class)->first()->otp;

    $verifyResponse = $this->postJson('/api/v1/auth/register/verify-otp', [
        'email' => $email,
        'otp' => $otp,
    ]);
    $verifyResponse->assertOk();
    $token = $verifyResponse->json('register_token');
    expect($token)->toHaveLength(60);

    $response = $this
        ->withHeader('Referer', 'http://localhost')
        ->postJson('/api/v1/auth/register', [
            'email' => $email,
            'password' => 'password',
            'register_token' => $token,
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.email', 'customer@example.com')
        ->assertJsonPath('data.role', AccountRole::Customer->value)
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissingPath('data.remember_token');

    $account = Account::query()->where('email', $email)->firstOrFail();

    expect(Hash::check('password', $account->password))->toBeTrue()
        ->and($account->role)->toBe(AccountRole::Customer)
        ->and($account->is_active)->toBeTrue()
        ->and($account->email_verified_at)->not->toBeNull();

    $this->assertDatabaseHas('user_profiles', [
        'account_id' => $account->id,
    ]);

    $this->assertAuthenticatedAs($account);
});

test('email must be unique when registering', function () {
    Account::factory()->create([
        'email' => 'customer@example.com',
    ]);

    $response = $this->postJson('/api/v1/auth/register', [
        'email' => 'customer@example.com',
        'password' => 'password',
        'register_token' => Str::random(60),
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

test('client cannot choose account role when registering', function () {
    Mail::fake();

    $email = 'customer@example.com';

    $this->postJson('/api/v1/auth/register/send-otp', ['email' => $email])->assertOk();
    $otp = Mail::queued(RegistrationOtpMail::class)->first()->otp;
    $token = $this->postJson('/api/v1/auth/register/verify-otp', [
        'email' => $email,
        'otp' => $otp,
    ])->json('register_token');

    $response = $this
        ->withHeader('Referer', 'http://localhost')
        ->postJson('/api/v1/auth/register', [
            'email' => $email,
            'password' => 'password',
            'register_token' => $token,
            'role' => AccountRole::Admin->value,
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.role', AccountRole::Customer->value);

    $this->assertDatabaseHas('accounts', [
        'email' => $email,
        'role' => AccountRole::Customer->value,
    ]);
});

test('register rejects invalid register token', function () {
    Mail::fake();

    $email = 'customer@example.com';

    $this->postJson('/api/v1/auth/register/send-otp', ['email' => $email])->assertOk();
    $otp = Mail::queued(RegistrationOtpMail::class)->first()->otp;
    $this->postJson('/api/v1/auth/register/verify-otp', [
        'email' => $email,
        'otp' => $otp,
    ])->assertOk();

    $response = $this
        ->withHeader('Referer', 'http://localhost')
        ->postJson('/api/v1/auth/register', [
            'email' => $email,
            'password' => 'password',
            'register_token' => Str::random(60),
        ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors('register_token')
        ->assertJsonFragment(['Yêu cầu xác thực email trước.']);

    expect(Account::query()->where('email', $email)->exists())->toBeFalse();
});

test('register requires register_token', function () {
    $response = $this
        ->withHeader('Referer', 'http://localhost')
        ->postJson('/api/v1/auth/register', [
            'email' => 'customer@example.com',
            'password' => 'password',
        ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('register_token');
});
