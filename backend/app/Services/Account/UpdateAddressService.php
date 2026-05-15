<?php

namespace App\Services\Account;

use App\Models\Account;
use App\Models\Address;
use App\Services\Location\LocationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class UpdateAddressService
{
    public function __construct(
        private readonly LocationService $locationService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Account $account, int $addressId, array $data): Address
    {
        $address = Address::query()
            ->where('id', $addressId)
            ->where('account_id', $account->id)
            ->firstOrFail();

        $provinceCode = (string) $data['province_code'];
        $wardCode = (string) $data['ward_code'];

        AssertAdministrativeAddressIntegrity::assertProvinceWard(
            $this->locationService,
            $provinceCode,
            $wardCode,
            'Address update',
            (int) $account->id
        );

        $wantsDefault = array_key_exists('is_default', $data);
        $wantDefaultValue = $wantsDefault ? (bool) $data['is_default'] : null;

        if ($wantsDefault && $wantDefaultValue === false && $address->is_default) {
            throw ValidationException::withMessages([
                'is_default' => ['Bạn phải đặt địa chỉ khác làm mặc định trước.'],
            ]);
        }

        try {
            return DB::transaction(function () use ($account, $address, $data, $provinceCode, $wardCode, $wantsDefault, $wantDefaultValue): Address {
                $isDefault = $address->is_default;

                if ($wantsDefault && $wantDefaultValue === true) {
                    $isDefault = true;
                    $account->addresses()->where('is_default', true)->where('id', '!=', $address->id)->update(['is_default' => false]);
                }

                $address->update([
                    'recipient_name' => $data['recipient_name'],
                    'recipient_phone' => $data['recipient_phone'],
                    'province_code' => $provinceCode,
                    'district_code' => null,
                    'ward_code' => $wardCode,
                    'detail_address' => $data['detail_address'],
                    'is_default' => $isDefault,
                ]);

                $address->refresh();

                return $address;
            });
        } catch (Throwable $e) {
            Log::error('Address update failed', [
                'account_id' => $account->id,
                'address_id' => $addressId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
