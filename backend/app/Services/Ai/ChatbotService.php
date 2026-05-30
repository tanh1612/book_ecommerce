<?php

namespace App\Services\Ai;

class ChatbotService
{
    /**
     * @return array{
     *     message_id: null,
     *     answer: string,
     *     sources: array<int, mixed>,
     *     meta: array<string, mixed>
     * }
     */
    public function buildStubResponse(string $sessionId): array
    {
        return [
            'message_id' => null,
            'answer' => (string) config('ai.chat.stub_message'),
            'sources' => [],
            'meta' => [
                'session_id' => $sessionId,
                'model' => config('ai.gemini.chat_model'),
                'retrieval' => [
                    'strategy' => 'none',
                    'top_score' => null,
                    'matched' => false,
                ],
                'evaluation' => null,
            ],
        ];
    }
}
