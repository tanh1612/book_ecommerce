<?php

namespace App\Services\Order;

use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Log;
use Throwable;

class OrderInventoryService
{
    /**
     * Release stock reservations held for a pending/cancelled order.
     * Caller should run inside a DB transaction when atomicity with order status matters.
     */
    public function releaseReservedForOrder(Order $order): void
    {
        try {
            $items = OrderItem::query()
                ->where('order_id', $order->id)
                ->get(['book_id', 'quantity']);

            foreach ($items as $item) {
                $bookId = (int) $item->book_id;
                $qty = (int) $item->quantity;

                $inventory = Inventory::query()
                    ->where('book_id', $bookId)
                    ->lockForUpdate()
                    ->first();

                if ($inventory === null) {
                    Log::warning('Cannot release reservation: inventory row missing', [
                        'order_id' => $order->id,
                        'book_id' => $bookId,
                    ]);

                    continue;
                }

                $reserved = (int) $inventory->reserved_quantity;
                $releaseQty = min($qty, max(0, $reserved));

                if ($releaseQty > 0) {
                    $inventory->decrement('reserved_quantity', $releaseQty);
                }
            }
        } catch (Throwable $e) {
            Log::error('Release reserved inventory for order failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
