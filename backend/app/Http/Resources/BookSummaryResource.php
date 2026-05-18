<?php

namespace App\Http\Resources;

use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Book
 */
class BookSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $availableStock = $this->availableStock();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'thumbnail_url' => $this->thumbnailUrl(),
            'original_price' => $this->original_price,
            'selling_price' => $this->selling_price,
            'average_rating' => $this->average_rating,
            'review_count' => $this->review_count,
            'is_active' => (bool) $this->is_active,
            'available_stock' => $availableStock,
            'in_stock' => $availableStock > 0,
            'authors' => $this->whenLoaded('authors', fn () => $this->authors->map(fn ($author) => [
                'id' => $author->id,
                'name' => $author->name,
            ])->values()),
            'categories' => $this->whenLoaded('categories', fn () => $this->categories->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ])->values()),
            'publisher' => $this->whenLoaded('publisher', fn () => $this->publisher === null ? null : [
                'id' => $this->publisher->id,
                'name' => $this->publisher->name,
            ]),
        ];
    }

    private function thumbnailUrl(): ?string
    {
        if ($this->relationLoaded('images') && $this->images->isNotEmpty()) {
            return $this->images->first()?->image_url;
        }

        $raw = $this->getRawOriginal('thumbnail');

        return $raw !== null && $raw !== '' ? (string) $raw : null;
    }

    private function availableStock(): int
    {
        if (! $this->relationLoaded('inventories')) {
            return 0;
        }

        return (int) $this->inventories->sum(function ($inventory): int {
            return max(0, (int) $inventory->quantity - (int) $inventory->reserved_quantity);
        });
    }
}
