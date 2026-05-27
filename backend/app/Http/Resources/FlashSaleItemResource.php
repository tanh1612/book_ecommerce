<?php

namespace App\Http\Resources;

use App\Models\Book;
use App\Models\PromotionItem;
use App\Services\Promotion\FlashSaleResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PromotionItem
 */
class FlashSaleItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PromotionItem $item */
        $item = $this->resource;
        /** @var Book $book */
        $book = $item->book;
        return [
            'promotion_item_id' => $item->id,
            'book' => [
                'id' => $book->id,
                'name' => $book->name,
                'slug' => $book->slug,
                'thumbnail_url' => $this->thumbnailUrl($book),
            ],
            'original_price' => (float) $book->original_price,
            'selling_price' => (float) $book->selling_price,
            'discount_percent' => (float) $item->discount_value,
            'stock_limit' => $item->stock_limit,
            'sold_quantity' => (int) $item->sold_quantity,
            'remaining_stock' => app(FlashSaleResolver::class)->displayRemainingStock($item),
        ];
    }

    private function thumbnailUrl(Book $book): ?string
    {
        if ($book->relationLoaded('images') && $book->images->isNotEmpty()) {
            return $book->images->first()?->image_url;
        }

        $raw = $book->getRawOriginal('thumbnail');

        return $raw !== null && $raw !== '' ? (string) $raw : null;
    }
}
