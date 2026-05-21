<?php

namespace App\Http\Resources;

use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Book
 */
class CartBookResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'thumbnail_url' => $this->thumbnailUrl(),
            'selling_price' => $this->selling_price,
            'original_price' => $this->original_price,
            'is_active' => (bool) $this->is_active,
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
}
