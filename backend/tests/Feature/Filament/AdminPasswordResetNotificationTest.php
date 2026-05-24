<?php

use App\Enums\Account\AccountRole;
use App\Models\Account;
use App\Notifications\Auth\AdminPasswordResetNotification;
use Filament\Auth\Notifications\ResetPassword as FilamentResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('filament resolves custom admin password reset notification', function (): void {
    $notification = app(FilamentResetPassword::class, ['token' => 'test-token']);

    expect($notification)->toBeInstanceOf(AdminPasswordResetNotification::class);
});

test('admin password reset notification uses bookify branded vietnamese mail', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);

    $notification = new AdminPasswordResetNotification('test-token');
    $notification->url = 'https://book_ecommerce.test/password-reset/reset?token=test';

    $mail = $notification->toMail($admin);

    expect($mail->subject)->toBe('Đặt lại mật khẩu Quản trị Bookify')
        ->and($mail->view)->toBe('emails.admin-password-reset')
        ->and($mail->viewData['url'])->toBe('https://book_ecommerce.test/password-reset/reset?token=test')
        ->and($mail->viewData['expireMinutes'])->toBe(60);

    $html = view($mail->view, $mail->viewData)->render();

    expect($html)
        ->toContain('Đặt lại mật khẩu')
        ->toContain('Bookify')
        ->toContain('https://book_ecommerce.test/password-reset/reset?token=test');
});
