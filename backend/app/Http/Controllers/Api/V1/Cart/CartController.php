<?php

namespace App\Http\Controllers\Api\V1\Cart;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\AddCartItemRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Models\CartItem;
use App\Services\Cart\CartService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CartController extends Controller
{
    public function show(CartService $cartService): CartResource
    {
        return new CartResource($cartService->getCurrentCartForApi());
    }

    public function addItem(AddCartItemRequest $request, CartService $cartService): JsonResponse
    {
        $validated = $request->validated();
        $cartService->addItem((int) $validated['book_id'], (int) $validated['quantity']);

        return (new CartResource($cartService->getCurrentCartForApi()))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function updateItem(UpdateCartItemRequest $request, CartItem $cartItem, CartService $cartService): CartResource
    {
        $validated = $request->validated();
        $cartService->updateItem($cartItem, $validated);

        return new CartResource($cartService->getCurrentCartForApi());
    }

    public function removeItem(CartItem $cartItem, CartService $cartService): CartResource
    {
        $cartService->removeItem($cartItem);

        return new CartResource($cartService->getCurrentCartForApi());
    }
}
