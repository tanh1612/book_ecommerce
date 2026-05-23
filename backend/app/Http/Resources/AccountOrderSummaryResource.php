<?php

namespace App\Http\Resources;

use App\Models\Book;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Account\CreateReviewService;
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
        $reviewService = app(CreateReviewService::class);

        return [
            'id' => $order->id,
            'payment_method' => $order->payment_method?->value,
            'payment_status' => $order->payment_status?->value,
            'payment_expires_at' => $order->payment_expires_at?->toIso8601String(),
            'can_pay' => $order->canPay(),
            'current_status' => $order->current_status?->value,
            'created_at' => $order->created_at?->toIso8601String(),
            'total_quantity' => (int) $items->sum('quantity'),
            'final_amount' => (float) $order->final_amount,
            'items' => $items->map(fn (OrderItem $item): array => [
                'review_target_id' => $item->id,
                'book_name' => $item->book?->name,
                'thumbnail_url' => $this->bookThumbnailUrl($item->book),
                'can_review' => $reviewService->canReviewOrderItem($order, $item),
            ])->values()->all(),
            'can_cancel' => $cancelEligibility['can_cancel'],
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
