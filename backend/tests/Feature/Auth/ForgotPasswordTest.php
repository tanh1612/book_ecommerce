<?php

use App\Mail\PasswordResetOtpMail;
use App\Models\Account;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutMiddleware(VerifyCsrfToken::class);
    Cache::flush();
});

test('password reset send otp queues mail for active account', function (): void {
    Mail::fake();

    Account::factory()->create([
        'email' => 'member@example.com',
    ]);

    $response = $this->postJson('/api/v1/auth/password/forgot/send-otp', [
        'email' => 'member@example.com',
    ]);

    $response->assertOk()->assertJsonPath(
        'message',
        'Nếu email tồn tại trong hệ thống, mã xác nhận đặt lại mật khẩu đã được gửi.',
    );

    Mail::assertQueued(PasswordResetOtpMail::class);
});

test('password reset send otp does not queue mail for unknown email', function (): void {
    Mail::fake();

    $response = $this->postJson('/api/v1/auth/password/forgot/send-otp', [
        'email' => 'nobody@example.com',
    ]);

    $response->assertOk();
    Mail::assertNothingQueued();
});

test('password reset send otp does not queue mail for inactive account', function (): void {
    Mail::fake();

    Account::factory()->create([
        'email' => 'locked@example.com',
        'is_active' => false,
    ]);

    $response = $this->postJson('/api/v1/auth/password/forgot/send-otp', [
        'email' => 'locked@example.com',
    ]);

    $response->assertOk();
    Mail::assertNothingQueued();
});

test('password reset send otp enforces cooldown for same email', function (): void {
    Mail::fake();

    Account::factory()->create([
        'email' => 'cooldown@example.com',
    ]);

    $this->postJson('/api/v1/auth/password/forgot/send-otp', [
        'email' => 'cooldown@example.com',
    ])->assertOk();

    $this->postJson('/api/v1/auth/password/forgot/send-otp', [
        'email' => 'cooldown@example.com',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

test('password reset send otp is throttled per ip', function (): void {
    Mail::fake();

    Account::factory()->create(['email' => 'u0@throttle.test']);
    Account::factory()->create(['email' => 'u1@throttle.test']);
    Account::factory()->create(['email' => 'u2@throttle.test']);
    Account::factory()->create(['email' => 'u3@throttle.test']);
    Account::factory()->create(['email' => 'u4@throttle.test']);

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/password/forgot/send-otp', [
            'email' => "u{$i}@throttle.test",
        ])->assertOk();
    }

    Account::factory()->create(['email' => 'u5@throttle.test']);

    $this->postJson('/api/v1/auth/password/forgot/send-otp', [
        'email' => 'u5@throttle.test',
    ])->assertStatus(429);
});

test('password reset verify otp returns reset token when correct', function (): void {
    Mail::fake();

    Account::factory()->create([
        'email' => 'member@example.com',
    ]);

    $this->postJson('/api/v1/auth/password/forgot/send-otp', [
        'email' => 'member@example.com',
    ])->assertOk();

    $otp = Mail::queued(PasswordResetOtpMail::class)->first()->otp;

    $verifyResponse = $this->postJson('/api/v1/auth/password/forgot/verify-otp', [
        'email' => 'member@example.com',
        'otp' => $otp,
    ]);

    $verifyResponse->assertOk();
    expect($verifyResponse->json('reset_token'))->toHaveLength(60);
});

test('password reset verify otp rejects wrong code', function (): void {
    Mail::fake();

    Account::factory()->create([
        'email' => 'member@example.com',
    ]);

    $this->postJson('/api/v1/auth/password/forgot/send-otp', [
        'email' => 'member@example.com',
    ])->assertOk();

    $this->postJson('/api/v1/auth/password/forgot/verify-otp', [
        'email' => 'member@example.com',
        'otp' => '000000',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('otp');
});

test('password reset verify otp locks after too many wrong attempts', function (): void {
    Mail::fake();

    Account::factory()->create([
        'email' => 'member@example.com',
    ]);

    $this->postJson('/api/v1/auth/password/forgot/send-otp', [
        'email' => 'member@example.com',
    ])->assertOk();

    $otp = Mail::queued(PasswordResetOtpMail::class)->first()->otp;

    for ($i = 0; $i < 4; $i++) {
        $this->postJson('/api/v1/auth/password/forgot/verify-otp', [
            'email' => 'member@example.com',
            'otp' => '000000',
        ])->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/password/forgot/verify-otp', [
        'email' => 'member@example.com',
        'otp' => '000000',
    ])
        ->assertUnprocessable()
        ->assertJsonFragment(['Bạn đã nhập sai quá nhiều lần. Vui lòng yêu cầu gửi lại mã.']);

    $this->postJson('/api/v1/auth/password/forgot/verify-otp', [
        'email' => 'member@example.com',
        'otp' => $otp,
    ])->assertUnprocessable();
});

test('password reset completes with valid reset token', function (): void {
    Mail::fake();

    $account = Account::factory()->create([
        'email' => 'member@example.com',
    ]);

    $this->postJson('/api/v1/auth/password/forgot/send-otp', [
        'email' => 'member@example.com',
    ])->assertOk();

    $otp = Mail::queued(PasswordResetOtpMail::class)->first()->otp;

    $resetToken = $this->postJson('/api/v1/auth/password/forgot/verify-otp', [
        'email' => 'member@example.com',
        'otp' => $otp,
    ])->json('reset_token');

    $this->postJson('/api/v1/auth/password/reset', [
        'email' => 'member@example.com',
        'reset_token' => $resetToken,
        'password' => 'new-password-123',
    ])
        ->assertOk()
        ->assertJsonPath('message', 'Mật khẩu đã được đặt lại thành công.');

    $account->refresh();

    expect(Hash::check('new-password-123', $account->password))->toBeTrue()
        ->and(Hash::check('password', $account->password))->toBeFalse();
});

test('password reset rejects invalid reset token', function (): void {
    Mail::fake();

    Account::factory()->create([
        'email' => 'member@example.com',
    ]);

    $this->postJson('/api/v1/auth/password/reset', [
        'email' => 'member@example.com',
        'reset_token' => str_repeat('a', 60),
        'password' => 'new-password-123',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reset_token');
});

test('password reset requires password', function (): void {
    Mail::fake();

    Account::factory()->create([
        'email' => 'member@example.com',
    ]);

    $this->postJson('/api/v1/auth/password/forgot/send-otp', [
        'email' => 'member@example.com',
    ])->assertOk();

    $otp = Mail::queued(PasswordResetOtpMail::class)->first()->otp;

    $resetToken = $this->postJson('/api/v1/auth/password/forgot/verify-otp', [
        'email' => 'member@example.com',
        'otp' => $otp,
    ])->json('reset_token');

    $this->postJson('/api/v1/auth/password/reset', [
        'email' => 'member@example.com',
        'reset_token' => $resetToken,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');
});

test('password reset send otp finds account by case insensitive email', function (): void {
    Mail::fake();

    Account::factory()->create([
        'email' => 'Member@Example.com',
    ]);

    $this->postJson('/api/v1/auth/password/forgot/send-otp', [
        'email' => 'member@example.com',
    ])->assertOk();

    Mail::assertQueued(PasswordResetOtpMail::class);
});
