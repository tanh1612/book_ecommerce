<?php

namespace App\Notifications\Auth;

use Filament\Auth\Notifications\ResetPassword as FilamentResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class AdminPasswordResetNotification extends FilamentResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $broker = config('auth.defaults.passwords');
        $expireMinutes = (int) config("auth.passwords.{$broker}.expire", 60);

        return (new MailMessage)
            ->subject('Đặt lại mật khẩu Quản trị Bookify')
            ->view('emails.admin-password-reset', [
                'url' => $this->url,
                'expireMinutes' => $expireMinutes,
                'recipientName' => method_exists($notifiable, 'getFilamentName')
                    ? $notifiable->getFilamentName()
                    : null,
            ]);
    }
}
