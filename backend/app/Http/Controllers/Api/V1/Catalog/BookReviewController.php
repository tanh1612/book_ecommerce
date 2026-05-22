<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ListBookReviewsRequest;
use App\Http\Resources\ReviewResource;
use App\Services\Catalog\BookReviewService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BookReviewController extends Controller
{
    public function index(
        string $slug,
        ListBookReviewsRequest $request,
        BookReviewService $bookReviewService,
    ): AnonymousResourceCollection {
        $perPage = (int) ($request->validated('per_page') ?? 10);

        $paginator = $bookReviewService->paginateApprovedReviewsBySlug($slug, $perPage);

        return ReviewResource::collection($paginator);
    }
}
