<?php

namespace App\Http\Resources;

use App\Models\Book;
use App\Models\Wishlist;
use App\Services\Catalog\BookStockAvailabilityService;
use App\Services\Promotion\FlashSaleCatalogService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;
use Throwable;

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
        $availability = $this->resolveAvailability();
        $accountId = $request->user()?->id !== null ? (int) $request->user()->id : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'thumbnail_url' => $this->thumbnailUrl(),
            'original_price' => $this->original_price,
            'selling_price' => $this->selling_price,
            'flash_sale' => app(FlashSaleCatalogService::class)->flashSalePayloadForBook(
                (int) $this->id,
                1,
                $accountId,
            ),
            'average_rating' => $this->average_rating,
            'review_count' => $this->review_count,
            'is_active' => (bool) $this->is_active,
            'is_in_wishlist' => $this->resolveIsInWishlist($request),
            'available_stock' => $availability['available_stock'],
            'in_stock' => $availability['in_stock'],
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

    /**
     * @return array{available_stock: int, in_stock: bool}
     */
    private function resolveAvailability(): array
    {
        if ($this->relationLoaded('inventories')) {
            $availableStock = (int) $this->inventories->sum(function ($inventory): int {
                return max(0, (int) $inventory->quantity - (int) $inventory->reserved_quantity);
            });

            return [
                'available_stock' => $availableStock,
                'in_stock' => $availableStock > 0,
            ];
        }

        return app(BookStockAvailabilityService::class)->getAvailability((int) $this->id);
    }

    private function resolveIsInWishlist(Request $request): bool
    {
        $accountId = $request->user()?->id;
        if ($accountId === null) {
            return false;
        }

        try {
            return Wishlist::query()
                ->where('account_id', (int) $accountId)
                ->where('book_id', (int) $this->id)
                ->exists();
        } catch (Throwable $e) {
            Log::warning('Wishlist lookup failed on book detail', [
                'account_id' => (int) $accountId,
                'book_id' => (int) $this->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
