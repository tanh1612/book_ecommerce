<?php

namespace App\Http\Controllers\Api\V1\Recommendation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recommendation\ListRecommendationsRequest;
use App\Http\Resources\RecommendedBookResource;
use App\Services\Recommendation\RecommendationService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RecommendationController extends Controller
{
    public function index(
        ListRecommendationsRequest $request,
        RecommendationService $recommendationService,
    ): AnonymousResourceCollection {
        $result = $recommendationService->getForYouFeed($request->validated('limit'));

        return RecommendedBookResource::collection($result['books'])
            ->additional([
                'meta' => [
                    'feed' => 'for_you',
                    'strategy' => $result['strategy'],
                ],
            ]);
    }
}
