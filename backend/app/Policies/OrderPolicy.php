<?php

namespace App\Policies;

use App\Enums\Account\AccountRole;
use App\Models\Account;
use App\Models\Order;

class OrderPolicy
{
    public function viewAny(Account $account): bool
    {
        return $account->role === AccountRole::Admin;
    }

    public function view(Account $account, Order $order): bool
    {
        if ($account->role === AccountRole::Admin) {
            return true;
        }

        return $order->account_id === $account->id;
    }

    public function submitRefundBankInfo(Account $account, Order $order): bool
    {
        return $order->account_id === $account->id;
    }
}
