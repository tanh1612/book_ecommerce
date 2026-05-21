<?php

namespace App\Http\Resources;

use App\Enums\Order\OrderStatus;
use App\Models\Book;
use App\Models\Order;
use App\Services\Order\OrderStatusTransitionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Order $resource
 */
class AccountOrderSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Order $order */
        $order = $this->resource;
        $items = $order->relationLoaded('items') ? $order->items : collect();

        $cancelEligibility = app(OrderStatusTransitionService::class)->customerCancelEligibility($order);

        return [
            'id' => $order->id,
            'current_status' => $order->current_status?->value,
            'created_at' => $order->created_at?->toIso8601String(),
            'total_quantity' => (int) $items->sum('quantity'),
            'final_amount' => (float) $order->final_amount,
            'items' => $items->map(fn ($item): array => [
                'book_name' => $item->book?->name,
                'thumbnail_url' => $this->bookThumbnailUrl($item->book),
            ])->values()->all(),
            'can_cancel' => $cancelEligibility['can_cancel'],
            'can_review' => $order->current_status === OrderStatus::COMPLETED
                && $items->contains(fn ($item): bool => ! $item->is_reviewed),
        ];
    }

    private function bookThumbnailUrl(?Book $book): ?string
    {
        if ($book === null) {
            return null;
        }

        if ($book->relationLoaded('images') && $book->images->isNotEmpty()) {
            return $book->images->first()?->image_url;
        }

        $raw = $book->getRawOriginal('thumbnail');

        return $raw !== null && $raw !== '' ? (string) $raw : null;
    }
}
