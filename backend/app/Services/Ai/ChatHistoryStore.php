<?php

namespace App\Services\Ai;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ChatHistoryStore
{
    public function key(string $sessionId): string
    {
        return 'chat:'.$sessionId;
    }

    /**
     * @return array<int, array{role: string, content: string, created_at: string}>
     */
    public function getAll(string $sessionId): array
    {
        $key = $this->key($sessionId);

        try {
            $messages = $this->cache()->get($key);

            if (! is_array($messages)) {
                return [];
            }

            return $this->normalizeMessages($messages);
        } catch (Throwable $e) {
            Log::warning('AI chat history read failed', [
                'session_id' => $sessionId,
                'key' => $key,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return [];
        }
    }

    /**
     * @return array<int, array{role: string, content: string, created_at: string}>
     */
    public function getRecentTurns(string $sessionId, ?int $maxTurns = null): array
    {
        $messages = $this->getAll($sessionId);

        if ($messages === []) {
            return [];
        }

        $maxTurns ??= (int) config('ai.chat.history_max_turns', 10);
        $maxMessages = max($maxTurns * 2, 0);

        if ($maxMessages === 0) {
            return [];
        }

        if (count($messages) <= $maxMessages) {
            return $messages;
        }

        return array_values(array_slice($messages, -$maxMessages));
    }

    public function appendExchange(string $sessionId, string $userContent, string $assistantContent): void
    {
        $key = $this->key($sessionId);
        $ttlSeconds = max((int) config('ai.chat.history_ttl_seconds', 86400), 1);
        $messages = $this->getAll($sessionId);

        $messages[] = $this->makeEntry('user', $userContent);
        $messages[] = $this->makeEntry('assistant', $assistantContent);

        try {
            $this->cache()->put($key, $messages, $ttlSeconds);
        } catch (Throwable $e) {
            Log::warning('AI chat history write failed', [
                'session_id' => $sessionId,
                'key' => $key,
                'message_count' => count($messages),
                'ttl_seconds' => $ttlSeconds,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }
    }

    /**
     * @return array{role: string, content: string, created_at: string}
     */
    private function makeEntry(string $role, string $content): array
    {
        return [
            'role' => $role,
            'content' => $content,
            'created_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<int, mixed>  $messages
     * @return array<int, array{role: string, content: string, created_at: string}>
     */
    private function normalizeMessages(array $messages): array
    {
        $normalized = [];

        foreach ($messages as $message) {
            if (! is_array($message)) {
                continue;
            }

            $role = $message['role'] ?? null;
            $content = $message['content'] ?? null;
            $createdAt = $message['created_at'] ?? null;

            if (! is_string($role) || ! is_string($content) || ! is_string($createdAt)) {
                continue;
            }

            $normalized[] = [
                'role' => $role,
                'content' => $content,
                'created_at' => $createdAt,
            ];
        }

        return $normalized;
    }

    private function cache(): Repository
    {
        return Cache::store((string) config('ai.chat.history_store', 'redis'));
    }
}
