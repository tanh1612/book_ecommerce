<?php

namespace App\Services\Shipping;

use App\Models\Account;
use App\Models\Address;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use Illuminate\Validation\ValidationException;

class ShippingQuoteService
{
    /**
     * @return array{shipping_method: ShippingMethod, shipping_fee: float}
     */
    public function quote(Account $account, int $shippingMethodId, ?int $addressId, ?string $provinceCode): array
    {
        $method = ShippingMethod::query()
            ->whereKey($shippingMethodId)
            ->where('is_active', true)
            ->first();

        if ($method === null) {
            throw ValidationException::withMessages([
                'shipping_method_id' => ['The selected shipping method is invalid or inactive.'],
            ]);
        }

        $resolvedProvince = $this->resolveProvinceCode($account, $addressId, $provinceCode);
        $fee = $this->resolveBaseFee((int) $method->id, $resolvedProvince);

        return [
            'shipping_method' => $method,
            'shipping_fee' => (float) $fee,
        ];
    }

    /**
     * Same lookup rules as storefront quote: exact province row, else fallback rate with null province_code.
     */
    public function resolveBaseFee(int $shippingMethodId, string $provinceCode): string
    {
        $exact = ShippingRate::query()
            ->where('shipping_method_id', $shippingMethodId)
            ->where('province_code', $provinceCode)
            ->first();

        $rate = $exact ?? ShippingRate::query()
            ->where('shipping_method_id', $shippingMethodId)
            ->whereNull('province_code')
            ->first();

        if ($rate === null) {
            throw ValidationException::withMessages([
                'shipping_method_id' => ['No shipping rate is configured for this province and method.'],
            ]);
        }

        return (string) $rate->base_fee;
    }

    private function resolveProvinceCode(Account $account, ?int $addressId, ?string $provinceCode): string
    {
        if ($addressId !== null) {
            $address = Address::query()
                ->where('account_id', $account->id)
                ->whereKey($addressId)
                ->first();

            if ($address === null) {
                throw ValidationException::withMessages([
                    'address_id' => ['Address not found.'],
                ]);
            }

            if ($address->province_code === null || $address->province_code === '') {
                throw ValidationException::withMessages([
                    'address_id' => ['Selected address is missing province_code; cannot compute shipping fee.'],
                ]);
            }

            return (string) $address->province_code;
        }

        return trim((string) $provinceCode);
    }
}
