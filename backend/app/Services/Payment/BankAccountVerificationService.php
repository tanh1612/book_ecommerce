<?php

namespace App\Services\Payment;

use App\Contracts\Payment\BankAccountVerifier;
use App\DataTransferObjects\Payment\VerifiedBankAccount;
use Illuminate\Validation\ValidationException;

class BankAccountVerificationService
{
    public function __construct(
        private readonly RefundBankCatalogService $bankCatalog,
    ) {}

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
    public function findBankByCode(string $bankCode): array
    {
        $bank = $this->bankCatalog->findByCode($bankCode);

        if (! $bank['lookup_supported']) {
            throw ValidationException::withMessages([
                'bank_code' => ['Ngân hàng không hỗ trợ tra cứu số tài khoản.'],
            ]);
        }

        $driver = (string) config('refund.verification.driver', 'log');

        if ($driver === 'appotapay' && $bank['appotapay_bank_code'] === null) {
            throw ValidationException::withMessages([
                'bank_code' => ['Ngân hàng chưa được cấu hình tra cứu.'],
            ]);
        }

        return $bank;
    }

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
        return $this->bankCatalog->banks();
    }

    public function verify(string $bankCode, string $accountNumber): VerifiedBankAccount
    {
        $bank = $this->findBankByCode($bankCode);
        $normalizedAccount = preg_replace('/\s+/', '', $accountNumber) ?? '';

        if (! preg_match('/^\d{6,19}$/', $normalizedAccount)) {
            throw ValidationException::withMessages([
                'account_number' => ['Số tài khoản phải gồm 6–19 chữ số.'],
            ]);
        }

        return $this->verifier()->verify(
            (int) $bank['bin'],
            $normalizedAccount,
            $bank['code'],
            $bank['short_name'],
        );
    }

    private function verifier(): BankAccountVerifier
    {
        $driver = (string) config('refund.verification.driver', 'log');

        return match ($driver) {
            'appotapay' => app(AppotaPayBankAccountVerifier::class),
            default => app(LogBankAccountVerifier::class),
        };
    }
}
