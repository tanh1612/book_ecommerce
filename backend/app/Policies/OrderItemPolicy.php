<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\OrderItem;

class OrderItemPolicy
{
    public function submitReview(Account $account, OrderItem $orderItem): bool
    {
        $orderItem->loadMissing('order');

        return $orderItem->order !== null
            && $orderItem->order->account_id === $account->id;
    }
}
