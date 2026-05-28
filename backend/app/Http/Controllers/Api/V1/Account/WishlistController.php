<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreWishlistItemRequest;
use App\Http\Resources\BookSuggestionResource;
use App\Models\Book;
use App\Services\Account\WishlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class WishlistController extends Controller
{
    public function index(Request $request, WishlistService $wishlistService): AnonymousResourceCollection
    {
        return BookSuggestionResource::collection($wishlistService->list($request->user()));
    }

    public function store(StoreWishlistItemRequest $request, WishlistService $wishlistService): JsonResponse
    {
        $wishlistService->add($request->user(), (int) $request->validated('book_id'));

        return response()->json([
            'message' => 'Đã thêm vào danh sách yêu thích.',
        ], Response::HTTP_OK);
    }

    public function destroy(Request $request, Book $book, WishlistService $wishlistService): Response
    {
        $wishlistService->remove($request->user(), $book);

        return response()->noContent();
    }
}
