<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookReviewEligibilityResource;
use App\Models\Account;
use App\Services\Account\CreateReviewService;
use App\Services\Catalog\BookCatalogService;
use Illuminate\Http\Request;

class BookReviewEligibilityController extends Controller
{
    public function show(
        string $slug,
        Request $request,
        BookCatalogService $bookCatalogService,
        CreateReviewService $createReviewService,
    ): BookReviewEligibilityResource {
        $account = $request->user();
        abort_unless($account instanceof Account, 401);

        $book = $bookCatalogService->getBookBySlug($slug);
        $eligibility = $createReviewService->reviewEligibilityForBook($account, $book);

        return new BookReviewEligibilityResource($eligibility);
    }
}
