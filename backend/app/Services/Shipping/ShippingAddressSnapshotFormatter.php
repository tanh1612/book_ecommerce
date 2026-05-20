<?php

namespace App\Services\Shipping;

use App\Services\Location\LocationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ShippingAddressSnapshotFormatter
{
    public function __construct(
        private LocationService $locationService,
    ) {}

    public function format(string $detailAddress, string $wardCode, string $provinceCode): string
    {
        $detailAddress = trim($detailAddress);
        $wardCode = trim($wardCode);
        $provinceCode = trim($provinceCode);

        try {
            $payload = $this->locationService->getNewFullAddress($provinceCode, $wardCode);
        } catch (Throwable $e) {
            Log::error('Shipping address snapshot: location API failed', [
                'province_code' => $provinceCode,
                'ward_code' => $wardCode,
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

        $wardLabel = sprintf(
            '%s %s',
            mb_strtolower(trim((string) ($ward['type'] ?? 'phường'))),
            trim((string) ($ward['name'] ?? '')),
        );

        $provinceLabel = sprintf(
            '%s %s',
            trim((string) ($province['type'] ?? '')),
            trim((string) ($province['name'] ?? '')),
        );

        return $detailAddress.', '.$wardLabel.', '.$provinceLabel;
    }
}
