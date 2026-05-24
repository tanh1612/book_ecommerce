<?php

namespace App\Services\Admin;

use App\Enums\Account\AccountRole;
use App\Models\Account;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class AdminNotificationService
{
    public function notifyActiveAdmins(BaseNotification $notification): void
    {
        try {
            $admins = Account::query()
                ->where('role', AccountRole::Admin)
                ->where('is_active', true)
                ->get();

            if ($admins->isEmpty()) {
                return;
            }

            Notification::send($admins, $notification);
        } catch (\Throwable $e) {
            Log::error('Admin notification failed', [
                'notification' => $notification::class,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
