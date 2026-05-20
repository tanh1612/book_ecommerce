<?php

namespace App\Contracts\Payment;

use App\DataTransferObjects\Payment\VerifiedBankAccount;

interface BankAccountVerifier
{
    public function verify(int $bankBin, string $accountNumber, string $bankCode, string $bankName): VerifiedBankAccount;
}
