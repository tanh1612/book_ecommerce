<?php

namespace App\Http\Resources;

use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class FlashSaleResource extends JsonResource
{
    /**
     * @param  array{campaign: Promotion, items: Collection<int, \App\Models\PromotionItem>}  $resource
     */
    public function __construct($resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array{campaign: Promotion, items: Collection<int, \App\Models\PromotionItem>} $payload */
        $payload = $this->resource;
        $campaign = $payload['campaign'];

        return [
            'id' => $campaign->id,
            'start_at' => $campaign->start_at?->toIso8601String(),
            'end_at' => $campaign->end_at?->toIso8601String(),
            'items' => FlashSaleItemResource::collection($payload['items']),
        ];
    }
}
