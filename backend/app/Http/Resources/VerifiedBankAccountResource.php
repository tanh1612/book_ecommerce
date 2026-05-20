<?php

namespace App\Http\Resources;

use App\DataTransferObjects\Payment\VerifiedBankAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property VerifiedBankAccount $resource
 */
class VerifiedBankAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var VerifiedBankAccount $verified */
        $verified = $this->resource;

        return [
            'bank_code' => $verified->bankCode,
            'bank_name' => $verified->bankName,
            'bank_bin' => $verified->bankBin,
            'account_number' => $verified->accountNumber,
            'account_holder' => $verified->accountHolder,
            'verified' => true,
            'provider' => $verified->provider,
        ];
    }
}
