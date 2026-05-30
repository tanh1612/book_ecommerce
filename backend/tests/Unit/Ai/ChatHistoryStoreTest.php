<?php

use App\Services\Ai\ChatHistoryStore;
use Illuminate\Support\Facades\Cache;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    Cache::store((string) config('ai.chat.history_store'))->flush();
});

function chatHistoryCache(): \Illuminate\Contracts\Cache\Repository
{
    return Cache::store((string) config('ai.chat.history_store'));
}

function chatHistoryStore(): ChatHistoryStore
{
    return app(ChatHistoryStore::class);
}

function chatHistoryKey(string $sessionId): string
{
    return 'chat:'.$sessionId;
}

test('appendExchange stores user and assistant messages', function (): void {
    $sessionId = '550e8400-e29b-41d4-a716-446655440000';

    chatHistoryStore()->appendExchange($sessionId, 'Cau hoi 1', 'Tra loi 1');

    $messages = chatHistoryStore()->getAll($sessionId);

    expect($messages)->toHaveCount(2)
        ->and($messages[0]['role'])->toBe('user')
        ->and($messages[0]['content'])->toBe('Cau hoi 1')
        ->and($messages[1]['role'])->toBe('assistant')
        ->and($messages[1]['content'])->toBe('Tra loi 1')
        ->and($messages[0]['created_at'])->not->toBeEmpty();
});

test('multiple exchanges accumulate in history', function (): void {
    $sessionId = '550e8400-e29b-41d4-a716-446655440000';
    $store = chatHistoryStore();

    $store->appendExchange($sessionId, 'Cau hoi 1', 'Tra loi 1');
    $store->appendExchange($sessionId, 'Cau hoi 2', 'Tra loi 2');

    expect($store->getAll($sessionId))->toHaveCount(4);
});

test('getRecentTurns returns only the latest turn window', function (): void {
    config(['ai.chat.history_max_turns' => 1]);

    $sessionId = '550e8400-e29b-41d4-a716-446655440000';
    $store = chatHistoryStore();

    $store->appendExchange($sessionId, 'Cau hoi 1', 'Tra loi 1');
    $store->appendExchange($sessionId, 'Cau hoi 2', 'Tra loi 2');

    $recent = $store->getRecentTurns($sessionId);

    expect($recent)->toHaveCount(2)
        ->and($recent[0]['content'])->toBe('Cau hoi 2')
        ->and($recent[1]['content'])->toBe('Tra loi 2');
});

test('appendExchange refreshes ttl so history survives within window', function (): void {
    config(['ai.chat.history_ttl_seconds' => 3600]);

    $sessionId = '550e8400-e29b-41d4-a716-446655440000';
    $key = chatHistoryKey($sessionId);

    chatHistoryStore()->appendExchange($sessionId, 'Cau hoi 1', 'Tra loi 1');

    $this->travel(3500)->seconds();

    chatHistoryStore()->appendExchange($sessionId, 'Cau hoi 2', 'Tra loi 2');

    expect(chatHistoryCache()->has($key))->toBeTrue()
        ->and(chatHistoryStore()->getAll($sessionId))->toHaveCount(4);
});

test('history expires after ttl without a refresh', function (): void {
    config(['ai.chat.history_ttl_seconds' => 60]);

    $sessionId = '550e8400-e29b-41d4-a716-446655440000';
    $key = chatHistoryKey($sessionId);

    chatHistoryStore()->appendExchange($sessionId, 'Cau hoi 1', 'Tra loi 1');

    $this->travel(61)->seconds();

    expect(chatHistoryCache()->has($key))->toBeFalse()
        ->and(chatHistoryStore()->getAll($sessionId))->toBe([]);
});
