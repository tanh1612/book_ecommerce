<?php

namespace App\Services\Payment;

use App\Exceptions\Payment\RefundBankCatalogUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class RefundBankCatalogService
{
    private const CACHE_KEY = 'refund:banks:v1';

    private const CACHE_BACKUP_KEY = 'refund:banks:v1:backup';

    public function __construct(
        private readonly AppotaPayBankCodeMapper $appotaPayBankCodeMapper,
    ) {}

    /**
     * @return list<array{
     *     code: string,
     *     name: string,
     *     short_name: string,
     *     bin: int,
     *     logo: string|null,
     *     lookup_supported: bool,
     *     transfer_supported: bool,
     *     appotapay_bank_code: string|null
     * }>
     */
    public function banks(): array
    {
        $ttl = max(3600, (int) config('refund.bank_catalog.cache_ttl_seconds', 86400));

        try {
            /** @var list<array<string, mixed>> $banks */
            $banks = Cache::remember(self::CACHE_KEY, $ttl, function (): array {
                return $this->fetchFromVietQr();
            });

            return $banks;
        } catch (Throwable $e) {
            Log::error('Refund bank catalog fetch failed', ['error' => $e->getMessage()]);

            /** @var list<array<string, mixed>>|null $stale */
            $stale = Cache::get(self::CACHE_KEY) ?? Cache::get(self::CACHE_BACKUP_KEY);

            if (is_array($stale) && $stale !== []) {
                return $stale;
            }

            throw new RefundBankCatalogUnavailableException;
        }
    }

    /**
     * @return array{
     *     code: string,
     *     name: string,
     *     short_name: string,
     *     bin: int,
     *     logo: string|null,
     *     lookup_supported: bool,
     *     transfer_supported: bool,
     *     appotapay_bank_code: string|null
     * }
     */
    public function findByCode(string $bankCode): array
    {
        $normalized = Str::upper(trim($bankCode));

        foreach ($this->banks() as $bank) {
            if ($bank['code'] === $normalized) {
                return $bank;
            }
        }

        throw ValidationException::withMessages([
            'bank_code' => ['Ngân hàng không được hỗ trợ.'],
        ]);
    }

    /**
     * @return list<string>
     */
    public function lookupSupportedCodes(): array
    {
        return collect($this->banks())
            ->filter(fn (array $bank): bool => $bank['lookup_supported'])
            ->pluck('code')
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchFromVietQr(): array
    {
        $url = (string) config('refund.bank_catalog.vietqr.banks_url', 'https://api.vietqr.io/v2/banks');

        try {
            $response = Http::timeout(10)->get($url);
        } catch (ConnectionException $e) {
            Log::error('VietQR bank catalog connection failed', ['error' => $e->getMessage()]);

            throw $e;
        } catch (Throwable $e) {
            Log::error('VietQR bank catalog request failed', ['error' => $e->getMessage()]);

            throw $e;
        }

        if (! $response->successful()) {
            Log::warning('VietQR bank catalog HTTP error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RefundBankCatalogUnavailableException('Không thể tải danh sách ngân hàng từ VietQR.');
        }

        /** @var array<string, mixed>|null $body */
        $body = $response->json();
        $items = $body['data'] ?? [];

        if (! is_array($items)) {
            throw new RefundBankCatalogUnavailableException('Dữ liệu danh sách ngân hàng không hợp lệ.');
        }

        $banks = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $code = Str::upper(trim((string) ($item['code'] ?? '')));
            $bin = (int) ($item['bin'] ?? 0);

            if ($code === '' || $bin <= 0) {
                continue;
            }

            $lookupSupported = (int) ($item['lookupSupported'] ?? 0) === 1;
            $transferSupported = (int) ($item['transferSupported'] ?? 0) === 1;
            $appotapayBankCode = $this->appotaPayBankCodeMapper->resolve($code);

            $banks[] = [
                'code' => $code,
                'name' => (string) ($item['name'] ?? $code),
                'short_name' => (string) ($item['shortName'] ?? $item['short_name'] ?? $item['name'] ?? $code),
                'bin' => $bin,
                'logo' => isset($item['logo']) ? (string) $item['logo'] : null,
                'lookup_supported' => $lookupSupported,
                'transfer_supported' => $transferSupported,
                'appotapay_bank_code' => $appotapayBankCode,
            ];
        }

        if ($banks === []) {
            throw new RefundBankCatalogUnavailableException('Danh sách ngân hàng trống.');
        }

        usort($banks, fn (array $a, array $b): int => strcmp($a['short_name'], $b['short_name']));

        $backupTtl = max(86400, (int) config('refund.bank_catalog.cache_ttl_seconds', 86400) * 2);
        Cache::put(self::CACHE_BACKUP_KEY, $banks, $backupTtl);

        return $banks;
    }
}
