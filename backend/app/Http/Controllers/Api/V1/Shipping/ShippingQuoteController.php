<?php

namespace App\Http\Controllers\Api\V1\Shipping;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shipping\ShippingQuoteRequest;
use App\Http\Resources\ShippingQuoteResource;
use App\Services\Shipping\ShippingQuoteService;
use Illuminate\Http\JsonResponse;

class ShippingQuoteController extends Controller
{
    public function store(ShippingQuoteRequest $request, ShippingQuoteService $quotes): JsonResponse
    {
        $validated = $request->validated();

        $payload = $quotes->quote(
            $request->user(),
            (int) $validated['shipping_method_id'],
            $request->filled('address_id') ? (int) $validated['address_id'] : null,
            $validated['province_code'] ?? null,
        );

        return (new ShippingQuoteResource($payload))->response();
    }
}
