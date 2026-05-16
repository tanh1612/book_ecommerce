<?php

namespace App\Http\Resources;

use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CartItem
 */
class CartItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $book = $this->book;
        $lineSubtotal = $book !== null
            ? round((float) $book->selling_price * (int) $this->quantity, 2)
            : 0.0;

        $availableStock = 0;
        if ($book !== null && $book->relationLoaded('inventories')) {
            $availableStock = (int) $book->inventories->sum(
                fn ($inv): int => max(0, (int) $inv->quantity - (int) $inv->reserved_quantity)
            );
        }

        return [
            'id' => $this->id,
            'book' => $this->when(
                $this->relationLoaded('book') && $book !== null,
                fn () => (new BookSummaryResource($book))->resolve()
            ),
            'quantity' => $this->quantity,
            'selected' => $this->selected,
            'line_subtotal' => $lineSubtotal,
            'available_stock' => $availableStock,
        ];
    }
}
