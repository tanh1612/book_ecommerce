<?php

namespace App\Http\Controllers\Api\V1\Recommendation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recommendation\TrackBookViewRequest;
use App\Models\Book;
use App\Services\Recommendation\InteractionTrackingService;
use Symfony\Component\HttpFoundation\Response;

class InteractionController extends Controller
{
    public function trackBookView(
        TrackBookViewRequest $request,
        Book $book,
        InteractionTrackingService $interactionTrackingService,
    ): Response {
        $interactionTrackingService->trackView(
            $request->user(),
            $book,
            $request->validated('source')
        );

        return response()->noContent();
    }
}
