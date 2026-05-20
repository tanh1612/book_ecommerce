<?php

namespace App\Services\Payment;

use Illuminate\Support\Str;

class AppotaPayBankCodeMapper
{
    /**
     * AppotaPay firm-banking codes that match VietQR short codes one-to-one.
     *
     * @var list<string>
     */
    private const DIRECT_CODES = [
        'VCB', 'BIDV', 'MB', 'ACB', 'VIB', 'SHB', 'OCB', 'LPB', 'SCB', 'IVB',
        'UOB', 'HSBC', 'CIMB', 'DBS', 'NCB', 'PBVN', 'VRB', 'COOPBANK',
    ];

    public function resolve(string $vietQrBankCode): ?string
    {
        $code = Str::upper(trim($vietQrBankCode));

        if ($code === '') {
            return null;
        }

        /** @var array<string, string> $overrides */
        $overrides = config('refund.appotapay_bank_code_map', []);

        if (isset($overrides[$code])) {
            return Str::upper($overrides[$code]);
        }

        if (in_array($code, self::DIRECT_CODES, true)) {
            return $code;
        }

        return null;
    }
}
