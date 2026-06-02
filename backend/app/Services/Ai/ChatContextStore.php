<?php

namespace App\Services\Ai;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class ChatContextStore
{
    /**
     * @return list<array{book_id: int, name: string, slug: string}>
     */
    public function getLastSources(string $sessionId): array
    {
        $key = $this->lastSourcesKey($sessionId);

        try {
            $sources = $this->cache()->get($key);

            if (! is_array($sources)) {
                return [];
            }

            return $this->normalizeSources($sources);
        } catch (Throwable $e) {
            Log::warning('AI chat last sources read failed', [
                'session_id' => $sessionId,
                'key' => $key,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return [];
        }
    }

    /**
     * @return array{book_id: int, name: string, slug: string}|null
     */
    public function getCurrentSource(string $sessionId): ?array
    {
        $key = $this->currentSourceKey($sessionId);

        try {
            $source = $this->cache()->get($key);

            if (! is_array($source)) {
                return null;
            }

            return $this->normalizeSources([$source])[0] ?? null;
        } catch (Throwable $e) {
            Log::warning('AI chat current source read failed', [
                'session_id' => $sessionId,
                'key' => $key,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return null;
        }
    }

    /**
     * @param  list<array{book_id: int, name: string, slug: string}>  $sources
     */
    public function putLastSources(string $sessionId, array $sources): void
    {
        $key = $this->lastSourcesKey($sessionId);
        $ttlSeconds = max((int) config('ai.chat.history_ttl_seconds', 86400), 1);

        try {
            $this->cache()->put($key, $this->normalizeSources($sources), $ttlSeconds);
        } catch (Throwable $e) {
            Log::warning('AI chat last sources write failed', [
                'session_id' => $sessionId,
                'key' => $key,
                'source_count' => count($sources),
                'ttl_seconds' => $ttlSeconds,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }
    }

    /**
     * @param  array{book_id: int, name: string, slug: string}|null  $source
     */
    public function putCurrentSource(string $sessionId, ?array $source): void
    {
        $key = $this->currentSourceKey($sessionId);
        $ttlSeconds = max((int) config('ai.chat.history_ttl_seconds', 86400), 1);

        try {
            if ($source === null) {
                $this->cache()->forget($key);

                return;
            }

            $normalized = $this->normalizeSources([$source]);
            if ($normalized === []) {
                $this->cache()->forget($key);

                return;
            }

            $this->cache()->put($key, $normalized[0], $ttlSeconds);
        } catch (Throwable $e) {
            Log::warning('AI chat current source write failed', [
                'session_id' => $sessionId,
                'key' => $key,
                'ttl_seconds' => $ttlSeconds,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }
    }

    public function lastSourcesKey(string $sessionId): string
    {
        return 'chat:'.$sessionId.':last_sources';
    }

    public function currentSourceKey(string $sessionId): string
    {
        return 'chat:'.$sessionId.':current_source';
    }

    /**
     * @param  array<int, mixed>  $sources
     * @return list<array{book_id: int, name: string, slug: string}>
     */
    private function normalizeSources(array $sources): array
    {
        $normalized = [];

        foreach ($sources as $source) {
            if (! is_array($source)) {
                continue;
            }

            $bookId = $source['book_id'] ?? null;
            $name = $source['name'] ?? null;
            $slug = $source['slug'] ?? null;

            if (! is_int($bookId) || ! is_string($name) || ! is_string($slug)) {
                continue;
            }

            $normalized[] = [
                'book_id' => $bookId,
                'name' => $name,
                'slug' => $slug,
            ];
        }

        return $normalized;
    }

    private function cache(): Repository
    {
        return Cache::store((string) config('ai.chat.history_store', 'redis'));
    }
}
