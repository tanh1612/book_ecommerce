<?php

namespace App\Http\Resources;

use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Cart
 */
class CartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Cart $cart */
        $cart = $this->resource;
        $items = $cart->relationLoaded('items') ? $cart->items : collect();

        $subtotal = 0.0;
        $selectedSubtotal = 0.0;
        $totalQuantity = 0;
        $selectedQuantity = 0;

        foreach ($items as $item) {
            /** @var CartItem $item */
            $book = $item->book;
            $line = $book !== null
                ? (float) $book->selling_price * (int) $item->quantity
                : 0.0;
            $subtotal += $line;
            $totalQuantity += (int) $item->quantity;
            if ($item->selected) {
                $selectedSubtotal += $line;
                $selectedQuantity += (int) $item->quantity;
            }
        }

        return [
            'id' => $cart->id,
            'items' => CartItemResource::collection($items),
            'subtotal' => round($subtotal, 2),
            'total_quantity' => $totalQuantity,
            'selected_subtotal' => round($selectedSubtotal, 2),
            'selected_quantity' => $selectedQuantity,
        ];
    }
}
