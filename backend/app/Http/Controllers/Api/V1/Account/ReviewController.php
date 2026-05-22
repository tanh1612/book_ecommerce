<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\OrderItem;
use App\Services\Account\CreateReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ReviewController extends Controller
{
    public function store(
        StoreReviewRequest $request,
        OrderItem $orderItem,
        CreateReviewService $createReviewService,
    ): JsonResponse {
        $this->authorize('submitReview', $orderItem);

        $account = $request->user();

        try {
            $review = $createReviewService->create(
                $account,
                $orderItem,
                $request->validated(),
            );
        } catch (ValidationException $e) {
            throw $e;
        }

        return (new ReviewResource($review))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
