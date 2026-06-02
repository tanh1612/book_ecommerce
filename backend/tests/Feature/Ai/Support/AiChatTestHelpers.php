<?php

use App\Services\Ai\BookRagRetriever;
use App\Services\Ai\Dto\BookRagRetrievalResult;
use Illuminate\Testing\TestResponse;

function bindDefaultBookRagRetriever(): void
{
    $mock = Mockery::mock(BookRagRetriever::class);
    $mock->shouldReceive('retrieve')->andReturn(new BookRagRetrievalResult(
        matched: false,
        topScore: null,
        documents: [],
        strategy: 'none',
    ));
    app()->instance(BookRagRetriever::class, $mock);
}

function validChatPayload(array $overrides = []): array
{
    return array_merge([
        'session_id' => '550e8400-e29b-41d4-a716-446655440000',
        'question' => 'Toi muon tim sach ve ky nang giao tiep',
    ], $overrides);
}

function postChat(array $payload = []): TestResponse
{
    return test()->postJson('/api/v1/ai/chat', validChatPayload($payload));
}

function fakeGeminiChatSuccess(string $answer = 'Ban co the tham khao sach ve ky nang giao tiep.'): void
{
    Illuminate\Support\Facades\Http::fake([
        '*:generateContent*' => Illuminate\Support\Facades\Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => $answer],
                        ],
                    ],
                ],
            ],
            'usageMetadata' => [
                'promptTokenCount' => 12,
                'candidatesTokenCount' => 18,
                'totalTokenCount' => 30,
            ],
        ], 200),
    ]);
}

function postFeedback(int $messageId, array $payload = []): TestResponse
{
    return test()->postJson("/api/v1/ai/messages/{$messageId}/feedback", $payload);
}

function assertChatSuccessContract(TestResponse $response, string $sessionId, bool $expectEvaluation = true): void
{
    $response
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'message_id',
                'answer',
                'sources',
            ],
            'meta' => [
                'session_id',
                'model',
                'retrieval' => [
                    'strategy',
                    'top_score',
                    'matched',
                ],
            ],
        ])
        ->assertJsonPath('meta.session_id', $sessionId)
        ->assertJsonPath('data.answer', fn ($answer) => is_string($answer) && $answer !== '')
        ->assertJsonPath('data.sources', fn ($sources) => is_array($sources));

    $messageId = $response->json('data.message_id');
    expect($messageId === null || (is_int($messageId) && $messageId > 0))->toBeTrue();

    if ($expectEvaluation) {
        $response->assertJsonStructure([
            'meta' => [
                'evaluation' => [
                    'verdict',
                    'groundedness_score',
                    'relevance_score',
                    'has_hallucination_risk',
                ],
            ],
        ]);
    }
}

function assertChatFallbackContract(
    TestResponse $response,
    string $sessionId,
    string $errorCode,
): void {
    $response
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'message_id',
                'answer',
                'sources',
            ],
            'meta' => [
                'session_id',
                'model',
                'retrieval' => [
                    'strategy',
                    'top_score',
                    'matched',
                ],
                'error_code',
            ],
        ])
        ->assertJsonPath('meta.session_id', $sessionId)
        ->assertJsonPath('meta.model', null)
        ->assertJsonPath('meta.evaluation', null)
        ->assertJsonPath('meta.error_code', $errorCode)
        ->assertJsonPath('data.sources', [])
        ->assertJsonPath('data.answer', config('ai.chat.fallback_message'));

    $messageId = $response->json('data.message_id');
    expect($messageId === null || (is_int($messageId) && $messageId > 0))->toBeTrue();
}

function assertFeedbackSavedContract(TestResponse $response): void
{
    $response
        ->assertOk()
        ->assertExactJson([
            'message' => 'Feedback saved.',
        ]);
}
