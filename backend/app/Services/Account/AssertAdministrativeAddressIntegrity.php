<?php

namespace App\Services\Account;

use App\Services\Location\LocationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

final class AssertAdministrativeAddressIntegrity
{
    /**
     * @throws HttpException
     * @throws ValidationException
     */
    public static function assertProvinceWard(LocationService $locationService, string $provinceCode, string $wardCode, string $logContext, int $accountId): void
    {
        try {
            $payload = $locationService->getNewFullAddress($provinceCode, $wardCode);
        } catch (Throwable $e) {
            Log::error($logContext.': location validation API failed', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);

            throw new HttpException(503, 'Không thể xác thực địa chỉ lúc này. Vui lòng thử lại sau.');
        }

        if (! ($payload['success'] ?? false)) {
            throw ValidationException::withMessages([
                'ward_code' => ['Phường/Xã không hợp lệ với Tỉnh/Thành đã chọn.'],
            ]);
        }

        /** @var array<string, mixed>|null $inner */
        $inner = is_array($payload['data'] ?? null) ? $payload['data'] : null;
        if ($inner === null) {
            throw ValidationException::withMessages([
                'ward_code' => ['Phường/Xã không hợp lệ với Tỉnh/Thành đã chọn.'],
            ]);
        }

        $province = is_array($inner['province'] ?? null) ? $inner['province'] : null;
        $ward = is_array($inner['ward'] ?? null) ? $inner['ward'] : null;
        if ($province === null || $ward === null) {
            throw ValidationException::withMessages([
                'ward_code' => ['Phường/Xã không hợp lệ với Tỉnh/Thành đã chọn.'],
            ]);
        }

        if (($province['code'] ?? '') !== $provinceCode || ($ward['code'] ?? '') !== $wardCode) {
            throw ValidationException::withMessages([
                'ward_code' => ['Phường/Xã không hợp lệ với Tỉnh/Thành đã chọn.'],
            ]);
        }

        if (isset($ward['province_code']) && (string) $ward['province_code'] !== $provinceCode) {
            throw ValidationException::withMessages([
                'ward_code' => ['Phường/Xã không hợp lệ với Tỉnh/Thành đã chọn.'],
            ]);
        }
    }
}
