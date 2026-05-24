<?php

namespace App\Services\Order;

use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Promotion\PromotionAllocationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class OrderInventoryService
{
    public function __construct(
        private PromotionAllocationService $promotionAllocation,
    ) {}

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

            $this->promotionAllocation->releaseForOrder($order);
        } catch (Throwable $e) {
            Log::error('Release reserved inventory for order failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Commit reserved stock to sold when an order is delivered.
     * Caller should run inside a DB transaction with the order status update.
     */
    public function fulfillDeliveredOrder(Order $order): void
    {
        try {
            $items = OrderItem::query()
                ->where('order_id', $order->id)
                ->get(['book_id', 'quantity']);

            foreach ($items as $item) {
                $bookId = (int) $item->book_id;
                $qty = (int) $item->quantity;

                if ($qty <= 0) {
                    continue;
                }

                $inventory = Inventory::query()
                    ->where('book_id', $bookId)
                    ->lockForUpdate()
                    ->first();

                if ($inventory === null) {
                    throw ValidationException::withMessages([
                        'inventory' => ["Không tìm thấy tồn kho cho sách #{$bookId}."],
                    ]);
                }

                $reserved = (int) $inventory->reserved_quantity;
                $onHand = (int) $inventory->quantity;

                if ($qty > $reserved) {
                    throw ValidationException::withMessages([
                        'inventory' => [
                            "Số lượng giữ hàng không đủ cho sách #{$bookId} (cần {$qty}, đang giữ {$reserved}).",
                        ],
                    ]);
                }

                if ($qty > $onHand) {
                    throw ValidationException::withMessages([
                        'inventory' => [
                            "Tồn kho không đủ cho sách #{$bookId} (cần {$qty}, còn {$onHand}).",
                        ],
                    ]);
                }

                $inventory->decrement('reserved_quantity', $qty);
                $inventory->decrement('quantity', $qty);
                $inventory->increment('sold_quantity', $qty);
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Fulfill delivered order inventory failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
