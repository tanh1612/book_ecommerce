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
        $sessionId = $request->validated('session_id');
        $payload = $chatbotService->buildStubResponse($sessionId);

        return (new ChatMessageResource($payload))
            ->additional(['meta' => $payload['meta']]);
    }
}
