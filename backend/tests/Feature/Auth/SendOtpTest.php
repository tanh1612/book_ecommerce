<?php

use App\Mail\RegistrationOtpMail;
use App\Models\Account;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
});

test('send otp succeeds for new email', function () {
    Mail::fake();

    $response = $this->postJson('/api/v1/auth/register/send-otp', [
        'email' => 'newuser@example.com',
    ]);

    $response->assertOk()->assertJsonPath('message', 'Mã xác nhận đã được gửi đến email của bạn.');

    Mail::assertQueued(RegistrationOtpMail::class);
});

test('send otp rejects existing account email', function () {
    Mail::fake();

    Account::factory()->create(['email' => 'taken@example.com']);

    $response = $this->postJson('/api/v1/auth/register/send-otp', [
        'email' => 'taken@example.com',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('email');
    Mail::assertNothingQueued();
});

test('send otp enforces cooldown for same email', function () {
    Mail::fake();

    $email = 'cooldown@example.com';

    $this->postJson('/api/v1/auth/register/send-otp', ['email' => $email])->assertOk();

    $response = $this->postJson('/api/v1/auth/register/send-otp', ['email' => $email]);

    $response->assertUnprocessable()->assertJsonValidationErrors('email');
});

test('send otp is throttled per ip', function () {
    Mail::fake();

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/register/send-otp', [
            'email' => "user{$i}@throttle.test",
        ])->assertOk();
    }

    $this->postJson('/api/v1/auth/register/send-otp', [
        'email' => 'user5@throttle.test',
    ])->assertStatus(429);
});
