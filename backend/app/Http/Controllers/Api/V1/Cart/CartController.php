<?php

namespace App\Http\Controllers\Api\V1\Cart;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\AddCartItemRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Http\Requests\Cart\UpdateCartItemsSelectionRequest;
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
        $cartItem = $cartService->addItem((int) $validated['book_id'], (int) $validated['quantity']);

        return response()->json([
            'message' => 'Đã thêm vào giỏ hàng.',
            'data' => [
                'cart_item_id' => $cartItem->id,
                'book_id' => $cartItem->book_id,
                'quantity' => $cartItem->quantity,
                ...$cartService->cartQuantitySummary(),
            ],
        ], Response::HTTP_CREATED);
    }

    public function updateItem(UpdateCartItemRequest $request, CartItem $cartItem, CartService $cartService): JsonResponse
    {
        $validated = $request->validated();
        $updatedItem = $cartService->updateItem($cartItem, $validated);

        return response()->json([
            'message' => 'Đã cập nhật giỏ hàng.',
            'data' => [
                'cart_item_id' => $updatedItem->id,
                'book_id' => $updatedItem->book_id,
                'quantity' => $updatedItem->quantity,
                'selected' => (bool) $updatedItem->selected,
                ...$cartService->cartQuantitySummary(),
            ],
        ]);
    }

    public function updateItemsSelection(UpdateCartItemsSelectionRequest $request, CartService $cartService): JsonResponse
    {
        $validated = $request->validated();
        $cartService->updateItemsSelection((bool) $validated['selected']);

        return response()->json([
            'message' => 'Đã cập nhật lựa chọn giỏ hàng.',
            'data' => $cartService->cartQuantitySummary(),
        ]);
    }

    public function removeItem(CartItem $cartItem, CartService $cartService): JsonResponse
    {
        $removedCartItemId = $cartService->removeItem($cartItem);

        return response()->json([
            'message' => 'Đã xóa sản phẩm khỏi giỏ hàng.',
            'data' => [
                'removed_cart_item_id' => $removedCartItemId,
                ...$cartService->cartQuantitySummary(),
            ],
        ]);
    }
}
