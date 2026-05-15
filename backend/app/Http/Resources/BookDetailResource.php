<?php

namespace App\Http\Resources;

use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Book
 */
class BookDetailResource extends JsonResource
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
            'detail' => $this->whenLoaded('detail', fn () => $this->detail === null ? null : [
                'description' => $this->detail->description,
                'language' => $this->detail->language?->value,
                'language_label' => $this->detail->language?->getLabel(),
                'translator' => $this->detail->translator,
                'publication_year' => $this->detail->publication_year,
                'weight' => $this->detail->weight,
                'dimensions' => $this->detail->dimensions,
                'num_pages' => $this->detail->num_pages,
                'format' => $this->detail->format?->value,
                'format_label' => $this->detail->format?->getLabel(),
            ]),
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn ($image) => [
                'id' => $image->id,
                'image_url' => $image->image_url,
                'sort_order' => $image->sort_order,
            ])->values()),
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
