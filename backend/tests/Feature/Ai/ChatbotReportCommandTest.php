<?php

use App\Enums\Ai\ChatFeedbackRating;
use App\Models\AiChatEvaluation;
use App\Models\AiChatFeedback;
use App\Models\AiChatMessage;
use App\Services\Ai\ChatbotOperationsReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function createReportChatMessage(array $attributes = [], ?Carbon $createdAt = null): AiChatMessage
{
    $message = AiChatMessage::query()->create([
        'session_id' => '550e8400-e29b-41d4-a716-446655440000',
        'account_id' => null,
        'question' => 'Question?',
        'answer' => 'Answer.',
        'model_version' => 'gemini-test',
        'retrieval_strategy' => 'none',
        'retrieval_matched' => false,
        'retrieval_top_score' => null,
        'retrieved_books' => null,
        'token_usage' => null,
        'latency_ms' => null,
        'error_code' => null,
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

test('chatbot report command runs on empty database', function (): void {
    $this->artisan('ai:chatbot:report')
        ->assertSuccessful()
        ->expectsOutputToContain('Messages (24h): 0')
        ->expectsOutputToContain('Messages (7d): 0')
        ->expectsOutputToContain('Retrieval matched rate (7d): n/a');
});

test('chatbot report aggregates matched rate verdict and feedback', function (): void {
    Carbon::setTestNow('2026-06-01 12:00:00');

    $matched = createReportChatMessage([
        'retrieval_strategy' => 'hybrid',
        'retrieval_matched' => true,
        'latency_ms' => 200,
        'token_usage' => ['prompt' => 100, 'candidates' => 50, 'total' => 150],
    ], now()->subHours(2));

    $unmatched = createReportChatMessage([
        'retrieval_strategy' => 'none',
        'retrieval_matched' => false,
        'error_code' => 'gemini_timeout',
    ], now()->subDays(2));

    AiChatEvaluation::query()->create([
        'message_id' => $matched->id,
        'groundedness_score' => 0.9,
        'relevance_score' => 0.9,
        'has_hallucination_risk' => false,
        'verdict' => 'pass',
        'risk_flags' => [],
        'evaluated_at' => now(),
    ]);

    AiChatEvaluation::query()->create([
        'message_id' => $unmatched->id,
        'groundedness_score' => 0.3,
        'relevance_score' => 0.3,
        'has_hallucination_risk' => true,
        'verdict' => 'fail',
        'risk_flags' => ['ungrounded_price'],
        'evaluated_at' => now(),
    ]);

    AiChatFeedback::query()->create([
        'message_id' => $matched->id,
        'session_id' => $matched->session_id,
        'account_id' => null,
        'rating' => ChatFeedbackRating::Up,
        'created_at' => now()->subHour(),
        'updated_at' => now()->subHour(),
    ]);

    AiChatFeedback::query()->create([
        'message_id' => $unmatched->id,
        'session_id' => $unmatched->session_id,
        'account_id' => null,
        'rating' => ChatFeedbackRating::Down,
        'created_at' => now()->subHour(),
        'updated_at' => now()->subHour(),
    ]);

    $report = app(ChatbotOperationsReportService::class)->build();

    expect($report['messages_24h'])->toBe(1)
        ->and($report['messages_7d'])->toBe(2)
        ->and($report['retrieval_matched_rate_7d'])->toBe(50.0)
        ->and($report['retrieval_strategy_7d'])->toMatchArray([
            'hybrid' => 1,
            'none' => 1,
        ])
        ->and($report['gemini_errors_7d'])->toMatchArray([
            'gemini_timeout' => 1,
        ])
        ->and($report['evaluation_verdict_7d'])->toMatchArray([
            'fail' => 1,
            'pass' => 1,
        ])
        ->and($report['hallucination_risk_count_7d'])->toBe(1)
        ->and($report['feedback_up_7d'])->toBe(1)
        ->and($report['feedback_down_7d'])->toBe(1)
        ->and($report['avg_latency_ms_7d'])->toBe(200.0)
        ->and($report['avg_token_total_7d'])->toBe(150.0);

    $this->artisan('ai:chatbot:report')
        ->assertSuccessful()
        ->expectsOutputToContain('Retrieval matched rate (7d): 50.00%')
        ->expectsOutputToContain('Feedback up (7d): 1')
        ->expectsOutputToContain('Feedback down (7d): 1');

    Carbon::setTestNow();
});
