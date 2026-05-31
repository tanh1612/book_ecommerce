<?php

namespace App\Http\Controllers\Api\V1\Ai;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\ChatRequest;
use App\Http\Resources\Ai\ChatMessageResource;
use App\Services\Ai\ChatbotService;

class ChatController extends Controller
{
    public function store(ChatRequest $request, ChatbotService $chatbotService): ChatMessageResource
    {
        $payload = $chatbotService->handle(
            sessionId: $request->validated('session_id'),
            question: $request->validated('question'),
            accountId: $request->user()?->id,
        );

        return (new ChatMessageResource($payload))
            ->additional(['meta' => $payload['meta']]);
    }
}
