<?php

namespace App\Services\Account;

use App\Models\Account;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class AccountDeletionService
{
    public function softDeleteInactive(Account $account, Account $actor): void
    {
        if ((int) $account->getKey() === (int) $actor->getKey()) {
            throw ValidationException::withMessages([
                'account' => ['Không thể xóa tài khoản đang đăng nhập.'],
            ]);
        }

        if ($account->is_active) {
            throw ValidationException::withMessages([
                'account' => ['Chỉ xóa được tài khoản đang bị khóa.'],
            ]);
        }

        if ($account->hasUnfinishedOrders()) {
            throw ValidationException::withMessages([
                'account' => ['Không thể xóa tài khoản còn đơn hàng chưa hoàn thành.'],
            ]);
        }

        try {
            $account->delete();
        } catch (Throwable $e) {
            Log::error('Account soft delete failed', [
                'account_id' => $account->id,
                'actor_id' => $actor->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
