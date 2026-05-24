<?php

namespace App\Http\Resources;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{order: Order, payment: array<string, mixed>|null} $resource
 */
class CheckoutResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Order $order */
        $order = $this->resource['order'];
        $payment = $this->resource['payment'] ?? null;

        $items = $order->relationLoaded('items') ? $order->items : collect();

        return [
            'order' => [
                'id' => $order->id,
                'account_id' => $order->account_id,
                'shipping_method_id' => $order->shipping_method_id,
                'total_amount' => (float) $order->total_amount,
                'shipping_fee' => (float) $order->shipping_fee,
                'final_amount' => (float) $order->final_amount,
                'shipping_name' => $order->shipping_name,
                'shipping_phone' => $order->shipping_phone,
                'shipping_address' => $order->shipping_address,
                'payment_method' => $order->payment_method?->value,
                'payment_status' => $order->payment_status?->value,
                'payment_expires_at' => $order->payment_expires_at?->toIso8601String(),
                'note' => $order->note,
                'current_status' => $order->current_status?->value,
                'items' => $items->map(function (OrderItem $item): array {
                    return [
                        'id' => $item->id,
                        'book_id' => $item->book_id,
                        'promotion_id' => $item->promotion_id,
                        'promotion_item_id' => $item->promotion_item_id,
                        'price' => (float) $item->price,
                        'quantity' => (int) $item->quantity,
                        'total_price' => (float) $item->total_price,
                        'discount_amount' => $item->discount_amount !== null ? (float) $item->discount_amount : null,
                    ];
                })->values()->all(),
            ],
            'payment' => $payment,
        ];
    }
}
