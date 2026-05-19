<?php

namespace App\Http\Controllers\Api\V1\Checkout;

use App\Http\Controllers\Controller;
use App\Http\Requests\Checkout\CheckoutRequest;
use App\Http\Resources\CheckoutResource;
use App\Services\Checkout\CheckoutService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CheckoutController extends Controller
{
    public function store(CheckoutRequest $request, CheckoutService $checkout): JsonResponse
    {
        $payload = $checkout->checkout(
            $request->user(),
            $request->validated(),
            (string) $request->ip(),
        );

        return (new CheckoutResource($payload))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
