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
     * @param  array<string, mixed>|null  $quote
     */
    public function __construct($resource, private ?array $quote = null)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $book = $this->book;
        $availableStock = 0;

        if ($book !== null && $book->relationLoaded('inventories')) {
            $availableStock = (int) $book->inventories->sum(
                fn ($inv): int => max(0, (int) $inv->quantity - (int) $inv->reserved_quantity)
            );
        }

        $quote = $this->quote ?? [
            'base_unit_price' => $book !== null ? (float) $book->selling_price : 0.0,
            'effective_unit_price' => $book !== null ? (float) $book->selling_price : 0.0,
            'discount_amount' => 0.0,
            'line_subtotal_before_discount' => $book !== null
                ? round((float) $book->selling_price * (int) $this->quantity, 2)
                : 0.0,
            'line_total' => $book !== null
                ? round((float) $book->selling_price * (int) $this->quantity, 2)
                : 0.0,
            'promotion' => null,
        ];

        return [
            'id' => $this->id,
            'book' => $this->when(
                $this->relationLoaded('book') && $book !== null,
                fn () => (new CartBookResource($book))->resolve()
            ),
            'quantity' => $this->quantity,
            'selected' => $this->selected,
            'base_unit_price' => $quote['base_unit_price'],
            'effective_unit_price' => $quote['effective_unit_price'],
            'discount_amount' => $quote['discount_amount'],
            'line_subtotal_before_discount' => $quote['line_subtotal_before_discount'],
            'line_total' => $quote['line_total'],
            'promotion' => $quote['promotion'],
            'available_stock' => $availableStock,
        ];
    }
}
