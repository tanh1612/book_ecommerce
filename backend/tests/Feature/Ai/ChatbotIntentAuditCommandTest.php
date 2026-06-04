<?php

use App\Enums\Ai\ChatFeedbackRating;
use App\Models\AiChatFeedback;
use App\Models\AiChatMessage;
use App\Services\Ai\ChatbotIntentAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('intent audit command prints unmatched and feedback down sections', function (): void {
    config(['ai.chat.no_context_message' => 'No context answer']);

    $message = AiChatMessage::query()->create([
        'session_id' => '550e8400-e29b-41d4-a716-446655440000',
        'account_id' => null,
        'question' => 'alo ban oi nghe duoc khong',
        'answer' => 'Some answer',
        'model_version' => 'gemini-test',
        'retrieval_strategy' => 'none',
        'retrieval_matched' => false,
        'retrieved_books' => null,
        'token_usage' => null,
        'latency_ms' => 10,
        'error_code' => null,
    ]);

    AiChatFeedback::query()->create([
        'message_id' => $message->id,
        'session_id' => $message->session_id,
        'account_id' => null,
        'rating' => ChatFeedbackRating::Down->value,
    ]);

    AiChatMessage::query()->create([
        'session_id' => '550e8400-e29b-41d4-a716-446655440001',
        'account_id' => null,
        'question' => 'co sach nao khong',
        'answer' => 'No context answer',
        'model_version' => 'gemini-test',
        'retrieval_strategy' => 'none',
        'retrieval_matched' => false,
        'retrieved_books' => null,
        'token_usage' => null,
        'latency_ms' => 12,
        'error_code' => null,
    ]);

    $this->artisan('ai:chatbot:intent-audit', ['--days' => 7, '--limit' => 5])
        ->assertSuccessful()
        ->expectsOutputToContain('alo ban oi nghe duoc khong')
        ->expectsOutputToContain('co sach nao khong')
        ->expectsOutputToContain('No-context answer samples');
});

test('intent audit gemini samples require token usage not only model version', function (): void {
    AiChatMessage::query()->create([
        'session_id' => '550e8400-e29b-41d4-a716-446655440002',
        'account_id' => null,
        'question' => 'chao ban',
        'answer' => 'Chào bạn, mình đang ở đây.',
        'model_version' => 'gemini-test',
        'retrieval_strategy' => 'none',
        'retrieval_matched' => false,
        'retrieved_books' => null,
        'token_usage' => null,
        'latency_ms' => 5,
        'error_code' => null,
    ]);

    AiChatMessage::query()->create([
        'session_id' => '550e8400-e29b-41d4-a716-446655440003',
        'account_id' => null,
        'question' => 'alo ban oi',
        'answer' => 'Tra loi tu Gemini.',
        'model_version' => 'gemini-test',
        'retrieval_strategy' => 'none',
        'retrieval_matched' => false,
        'retrieved_books' => null,
        'token_usage' => [
            'prompt' => 10,
            'candidates' => 8,
            'total' => 18,
        ],
        'latency_ms' => 20,
        'error_code' => null,
    ]);

    $report = app(ChatbotIntentAuditService::class)->build(7, 10);

    expect($report['short_unmatched_gemini_samples'])
        ->not->toContain('chao ban')
        ->toContain('alo ban oi');
});
