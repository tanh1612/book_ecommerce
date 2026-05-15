<?php

namespace App\Services\Account;

use App\Models\Account;
use App\Models\Address;
use App\Services\Location\LocationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

        AssertAdministrativeAddressIntegrity::assertProvinceWard(
            $this->locationService,
            $provinceCode,
            $wardCode,
            'Address create',
            (int) $account->id
        );

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
