<?php

use App\Mail\RegistrationOtpMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
});

test('verify otp returns register token when code is correct', function () {
    Mail::fake();

    $email = 'verify@example.com';

    $this->postJson('/api/v1/auth/register/send-otp', ['email' => $email])->assertOk();
    $otp = Mail::queued(RegistrationOtpMail::class)->first()->otp;

    $response = $this->postJson('/api/v1/auth/register/verify-otp', [
        'email' => $email,
        'otp' => $otp,
    ]);

    $response->assertOk();
    expect($response->json('register_token'))->toHaveLength(60);
});

test('verify otp rejects wrong code', function () {
    Mail::fake();

    $email = 'wrongotp@example.com';

    $this->postJson('/api/v1/auth/register/send-otp', ['email' => $email])->assertOk();

    $response = $this->postJson('/api/v1/auth/register/verify-otp', [
        'email' => $email,
        'otp' => '000000',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('otp');
});

test('verify otp rejects expired or missing otp', function () {
    $response = $this->postJson('/api/v1/auth/register/verify-otp', [
        'email' => 'never-sent@example.com',
        'otp' => '123456',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors('otp');
});
