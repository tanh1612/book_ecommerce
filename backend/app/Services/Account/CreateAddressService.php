<?php

namespace App\Services\Account;

use App\Models\Account;
use App\Models\Address;
use App\Services\Location\LocationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class CreateAddressService
{
    public function __construct(
        private readonly LocationService $locationService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Account $account, array $data): Address
    {
        $provinceCode = (string) $data['province_code'];
        $wardCode = (string) $data['ward_code'];

        try {
            $payload = $this->locationService->getNewFullAddress($provinceCode, $wardCode);
        } catch (Throwable $e) {
            Log::error('Address create: location validation API failed', [
                'account_id' => $account->id,
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

        try {
            return DB::transaction(function () use ($account, $data, $provinceCode, $wardCode): Address {
                $isFirst = ! $account->addresses()->exists();
                $wantDefault = (bool) ($data['is_default'] ?? false);
                $isDefault = $isFirst || $wantDefault;

                if ($isDefault) {
                    $account->addresses()->where('is_default', true)->update(['is_default' => false]);
                }

                return $account->addresses()->create([
                    'recipient_name' => $data['recipient_name'],
                    'recipient_phone' => $data['recipient_phone'],
                    'province_code' => $provinceCode,
                    'district_code' => null,
                    'ward_code' => $wardCode,
                    'detail_address' => $data['detail_address'],
                    'is_default' => $isDefault,
                ]);
            });
        } catch (Throwable $e) {
            Log::error('Address create failed', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
