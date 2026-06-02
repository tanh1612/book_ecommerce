<?php

use App\Enums\Ai\ChatFeedbackRating;
use App\Models\AiChatEvaluation;
use App\Models\AiChatFeedback;
use App\Models\AiChatMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function createPruneChatMessage(array $attributes = [], ?Carbon $createdAt = null): AiChatMessage
{
    $message = AiChatMessage::query()->create([
        'session_id' => '550e8400-e29b-41d4-a716-446655440010',
        'account_id' => null,
        'question' => 'Question?',
        'answer' => 'Answer.',
        'model_version' => 'gemini-test',
        'retrieval_strategy' => 'none',
        'retrieval_matched' => false,
        ...$attributes,
    ]);

    if ($createdAt !== null) {
        $message->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->saveQuietly();
        $message->refresh();
    }

    return $message;
}

test('chatbot prune deletes old messages and cascades evaluation and feedback', function (): void {
    Carbon::setTestNow('2026-06-01 12:00:00');

    $oldMessage = createPruneChatMessage([], now()->subDays(120));

    AiChatEvaluation::query()->create([
        'message_id' => $oldMessage->id,
        'groundedness_score' => 0.8,
        'relevance_score' => 0.8,
        'has_hallucination_risk' => false,
        'verdict' => 'pass',
        'risk_flags' => [],
        'evaluated_at' => now()->subDays(120),
    ]);

    AiChatFeedback::query()->create([
        'message_id' => $oldMessage->id,
        'session_id' => $oldMessage->session_id,
        'account_id' => null,
        'rating' => ChatFeedbackRating::Up,
        'created_at' => now()->subDays(120),
        'updated_at' => now()->subDays(120),
    ]);

    $this->artisan('ai:chatbot:prune', ['--days' => 90])
        ->assertSuccessful()
        ->expectsOutputToContain('Pruned 1 chat message(s)');

    expect(AiChatMessage::query()->count())->toBe(0)
        ->and(AiChatEvaluation::query()->count())->toBe(0)
        ->and(AiChatFeedback::query()->count())->toBe(0);

    Carbon::setTestNow();
});

test('chatbot prune does not delete messages within retention window', function (): void {
    Carbon::setTestNow('2026-06-01 12:00:00');

    $recentMessage = createPruneChatMessage([], now()->subDays(10));

    AiChatEvaluation::query()->create([
        'message_id' => $recentMessage->id,
        'groundedness_score' => 0.8,
        'relevance_score' => 0.8,
        'has_hallucination_risk' => false,
        'verdict' => 'warning',
        'risk_flags' => [],
        'evaluated_at' => now()->subDays(10),
    ]);

    $this->artisan('ai:chatbot:prune', ['--days' => 90])
        ->assertSuccessful()
        ->expectsOutputToContain('Pruned 0 chat message(s)');

    expect(AiChatMessage::query()->count())->toBe(1)
        ->and(AiChatEvaluation::query()->count())->toBe(1);

    Carbon::setTestNow();
});
