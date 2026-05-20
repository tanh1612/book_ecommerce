<?php

namespace App\DataTransferObjects\Payment;

readonly class VerifiedBankAccount
{
    public function __construct(
        public string $bankCode,
        public string $bankName,
        public int $bankBin,
        public string $accountNumber,
        public string $accountHolder,
        public string $provider,
        public string $providerCode,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toBankInfoPayload(int $submittedByAccountId): array
    {
        return [
            'bank_code' => $this->bankCode,
            'bank_name' => $this->bankName,
            'bank_bin' => $this->bankBin,
            'account_number' => $this->accountNumber,
            'account_holder' => $this->accountHolder,
            'verification' => [
                'provider' => $this->provider,
                'status' => 'verified',
                'provider_code' => $this->providerCode,
                'verified_at' => now()->toIso8601String(),
            ],
            'submitted_at' => now()->toIso8601String(),
            'submitted_by_account_id' => $submittedByAccountId,
        ];
    }
}
