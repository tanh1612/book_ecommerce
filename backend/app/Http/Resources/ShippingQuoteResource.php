<?php

namespace App\Http\Resources;

use App\Models\ShippingMethod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{shipping_method: ShippingMethod, shipping_fee: float} $resource
 */
class ShippingQuoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ShippingMethod $method */
        $method = $this->resource['shipping_method'];

        return [
            'shipping_method' => [
                'id' => $method->id,
                'name' => $method->name,
            ],
            'shipping_fee' => $this->resource['shipping_fee'],
        ];
    }
}
