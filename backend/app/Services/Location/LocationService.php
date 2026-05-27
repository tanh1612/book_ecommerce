<?php

namespace App\Services\Location;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class LocationService
{
    private const CACHE_BUST_KEY = 'locations:cache_bust';

    private const STALE_KEY_SUFFIX = ':stale';

    /**
     * Bump cache bust counter so all location cache keys become stale without scanning stores.
     */
    public function invalidateCaches(): int
    {
        try {
            return (int) Cache::increment(self::CACHE_BUST_KEY);
        } catch (Throwable $e) {
            Log::warning('locations:cache_bust increment failed', [
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return 0;
        }
    }

    /**
     * @return array{success: bool, data: mixed, metadata?: array<string, mixed>}
     */
    public function getNewProvinces(?string $keyword, ?int $limit, ?int $page): array
    {
        $query = array_filter([
            'keyword' => $keyword,
            'limit' => $limit,
            'page' => $page,
        ], fn (mixed $v): bool => $v !== null && $v !== '');

        $cacheKey = $this->cacheKey('new_provinces', $query);

        return $this->rememberLocationPayload($cacheKey, fn (): array => $this->getJson('new-provinces', $query));
    }

    /**
     * @return array{success: bool, data: mixed, metadata?: array<string, mixed>}
     */
    public function getNewProvinceWards(string $provinceCode, ?string $keyword, ?int $limit, ?int $page): array
    {
        $query = array_filter([
            'keyword' => $keyword,
            'limit' => $limit,
            'page' => $page,
        ], fn (mixed $v): bool => $v !== null && $v !== '');

        $cacheKey = $this->cacheKey('new_wards', array_merge(['province' => $provinceCode], $query));

        return $this->rememberLocationPayload(
            $cacheKey,
            fn (): array => $this->getJson("new-provinces/{$provinceCode}/wards", $query),
        );
    }

    /**
     * @return array{success: bool, data?: array<string, mixed>}
     */
    public function getNewFullAddress(string $provinceCode, string $wardCode): array
    {
        $query = [
            'provinceCode' => $provinceCode,
            'wardCode' => $wardCode,
        ];

        $cacheKey = $this->cacheKey('new_full_address', $query);

        return $this->rememberLocationPayload(
            $cacheKey,
            fn (): array => $this->getJson('new-full-address', $query),
        );
    }

    /**
     * @param  callable(): array<string, mixed>  $fetchUpstream
     * @return array<string, mixed>
     */
    private function rememberLocationPayload(string $cacheKey, callable $fetchUpstream): array
    {
        $primary = $this->readLocationCache($cacheKey);
        if ($primary !== null) {
            return $primary;
        }

        try {
            $fresh = $fetchUpstream();
            $this->storeLocationCaches($cacheKey, $fresh);

            return $fresh;
        } catch (Throwable $e) {
            Log::warning('Location upstream fetch failed', [
                'key' => $cacheKey,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            $stale = $this->readLocationCache($this->staleCacheKey($cacheKey));
            if ($stale !== null) {
                Log::info('Location stale cache fallback used', ['key' => $cacheKey]);

                return $stale;
            }

            throw $e;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readLocationCache(string $key): ?array
    {
        try {
            $value = Cache::get($key);

            return is_array($value) ? $value : null;
        } catch (Throwable $e) {
            Log::warning('Location cache read failed', [
                'key' => $key,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function storeLocationCaches(string $cacheKey, array $payload): void
    {
        try {
            Cache::put($cacheKey, $payload, $this->cacheTtl());
            Cache::put($this->staleCacheKey($cacheKey), $payload, $this->staleCacheTtl());
        } catch (Throwable $e) {
            Log::warning('Location cache write failed', [
                'key' => $cacheKey,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }
    }

    private function staleCacheKey(string $primaryKey): string
    {
        return $primaryKey.self::STALE_KEY_SUFFIX;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{success: bool, data: mixed, metadata?: array<string, mixed>}
     */
    private function getJson(string $path, array $query): array
    {
        try {
            $response = $this->http()->get($path, $query);

            if (! $response->successful()) {
                Log::error('TinhThanhPho API non-success status', [
                    'path' => $path,
                    'query' => $query,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \RuntimeException('TinhThanhPho API returned an error response.');
            }

            /** @var array{success?: bool, data?: mixed, metadata?: array<string, mixed>} $decoded */
            $decoded = $response->json();

            if (! is_array($decoded)) {
                Log::error('TinhThanhPho API invalid JSON', ['path' => $path, 'query' => $query]);

                throw new \RuntimeException('TinhThanhPho API returned invalid JSON.');
            }

            return $decoded;
        } catch (ConnectionException|RequestException $e) {
            Log::error('TinhThanhPho API request failed', [
                'path' => $path,
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } catch (Throwable $e) {
            Log::error('TinhThanhPho API unexpected failure', [
                'path' => $path,
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    private function cacheKey(string $type, array $parts): string
    {
        $version = config('tinh_thanh_pho.cache_key_version', 'v2025');

        $bust = 0;
        try {
            $bust = (int) Cache::get(self::CACHE_BUST_KEY, 0);
        } catch (Throwable $e) {
            Log::warning('locations:cache_bust read failed', [
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }

        ksort($parts);

        return sprintf(
            'locations:%s:%d:%s:%s',
            $version,
            $bust,
            $type,
            md5((string) json_encode($parts, JSON_THROW_ON_ERROR))
        );
    }

    private function cacheTtl(): \DateTimeInterface|\DateInterval|int
    {
        return max(60, (int) config('tinh_thanh_pho.cache_ttl_seconds', 604800));
    }

    private function staleCacheTtl(): \DateTimeInterface|\DateInterval|int
    {
        return max(60, (int) config('tinh_thanh_pho.stale_cache_ttl_seconds', 60 * 60 * 24 * 30));
    }

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        $baseUrl = (string) config('tinh_thanh_pho.base_url', 'https://tinhthanhpho.com/api/v1');
        $timeout = (int) config('tinh_thanh_pho.timeout', 10);

        $pending = Http::baseUrl($baseUrl)
            ->timeout($timeout)
            ->acceptJson();

        $apiKey = config('tinh_thanh_pho.api_key');
        if (is_string($apiKey) && $apiKey !== '') {
            $pending = $pending->withToken($apiKey);
        }

        return $pending;
    }
}
