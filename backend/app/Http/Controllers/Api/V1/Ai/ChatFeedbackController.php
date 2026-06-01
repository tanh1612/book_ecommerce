<?php

namespace App\Http\Controllers\Api\V1\Ai;

use App\Enums\Ai\ChatFeedbackRating;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\ChatFeedbackRequest;
use App\Models\AiChatMessage;
use App\Services\Ai\ChatFeedbackService;
use Illuminate\Http\JsonResponse;

class ChatFeedbackController extends Controller
{
    public function store(
        ChatFeedbackRequest $request,
        AiChatMessage $message,
        ChatFeedbackService $chatFeedbackService,
    ): JsonResponse {
        $chatFeedbackService->upsert(
            message: $message,
            rating: ChatFeedbackRating::from($request->validated('rating')),
            sessionId: $request->validated('session_id'),
            authenticatedAccountId: $request->user()?->id,
        );

        return response()->json([
            'message' => 'Feedback saved.',
        ]);
    }
}
