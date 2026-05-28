<?php

namespace App\Services\Recommendation;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecommendationCacheService
{
    public function popularKey(): string
    {
        return 'reco:popular';
    }

    public function userKey(int $accountId): string
    {
        return sprintf('reco:user:%d', $accountId);
    }

    /**
     * @return array{strategy: string, generated_at: string, book_ids: array<int, int>}|null
     */
    public function getPopular(): ?array
    {
        $key = $this->popularKey();

        try {
            $payload = Cache::get($key);

            if (! is_array($payload)) {
                return null;
            }

            return $this->normalizePayload($payload);
        } catch (Throwable $e) {
            Log::warning('Recommendation popular cache read failed', [
                'key' => $key,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return null;
        }
    }

    /**
     * @param  array<int, int>  $bookIds
     */
    public function putPopular(array $bookIds): void
    {
        $key = $this->popularKey();
        $ttlSeconds = max((int) config('recommendation.popular_ttl_seconds', 21600), 1);
        $payload = [
            'strategy' => 'popular',
            'generated_at' => now()->toIso8601String(),
            'book_ids' => array_values(array_unique(array_map('intval', $bookIds))),
        ];

        try {
            Cache::put($key, $payload, $ttlSeconds);
        } catch (Throwable $e) {
            Log::error('Recommendation popular cache write failed', [
                'key' => $key,
                'book_ids_count' => count($payload['book_ids']),
                'ttl_seconds' => $ttlSeconds,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            throw $e;
        }
    }

    public function forgetPopular(): void
    {
        $key = $this->popularKey();

        try {
            Cache::forget($key);
        } catch (Throwable $e) {
            Log::warning('Recommendation popular cache forget failed', [
                'key' => $key,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }
    }

    /**
     * @return array{strategy: string, generated_at: string, book_ids: array<int, int>}|null
     */
    public function getUser(int $accountId): ?array
    {
        $key = $this->userKey($accountId);

        try {
            $payload = Cache::get($key);

            if (! is_array($payload)) {
                return null;
            }

            return $this->normalizePayload($payload);
        } catch (Throwable $e) {
            Log::warning('Recommendation user cache read failed', [
                'key' => $key,
                'account_id' => $accountId,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return null;
        }
    }

    /**
     * @param  array<int, int>  $bookIds
     */
    public function putUser(int $accountId, array $bookIds, string $strategy = 'content_based'): void
    {
        $key = $this->userKey($accountId);
        $ttlSeconds = max((int) config('recommendation.personalized_ttl_seconds', 3600), 1);
        $payload = [
            'strategy' => $strategy,
            'generated_at' => now()->toIso8601String(),
            'book_ids' => array_values(array_unique(array_map('intval', $bookIds))),
        ];

        try {
            Cache::put($key, $payload, $ttlSeconds);
        } catch (Throwable $e) {
            Log::error('Recommendation user cache write failed', [
                'key' => $key,
                'account_id' => $accountId,
                'book_ids_count' => count($payload['book_ids']),
                'ttl_seconds' => $ttlSeconds,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            throw $e;
        }
    }

    public function forgetUser(int $accountId): void
    {
        $key = $this->userKey($accountId);

        try {
            Cache::forget($key);
        } catch (Throwable $e) {
            Log::warning('Recommendation user cache forget failed', [
                'key' => $key,
                'account_id' => $accountId,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{strategy: string, generated_at: string, book_ids: array<int, int>}
     */
    private function normalizePayload(array $payload): array
    {
        $bookIds = [];
        foreach ($payload['book_ids'] ?? [] as $bookId) {
            $id = (int) $bookId;
            if ($id > 0) {
                $bookIds[] = $id;
            }
        }

        return [
            'strategy' => (string) ($payload['strategy'] ?? 'popular'),
            'generated_at' => (string) ($payload['generated_at'] ?? now()->toIso8601String()),
            'book_ids' => array_values(array_unique($bookIds)),
        ];
    }
}
