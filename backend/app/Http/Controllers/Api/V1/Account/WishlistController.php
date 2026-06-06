<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreWishlistItemRequest;
use App\Http\Resources\BookSummaryResource;
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
        return BookSummaryResource::collection($wishlistService->list($request->user()));
    }

    public function store(StoreWishlistItemRequest $request, WishlistService $wishlistService): JsonResponse
    {
        $book = $wishlistService->add($request->user(), (int) $request->validated('book_id'));

        return (new BookSummaryResource($book))
            ->additional([
                'message' => 'Đã thêm vào danh sách yêu thích.',
            ])
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function destroy(Request $request, Book $book, WishlistService $wishlistService): Response
    {
        $wishlistService->remove($request->user(), $book);

        return response()->noContent();
    }
}
