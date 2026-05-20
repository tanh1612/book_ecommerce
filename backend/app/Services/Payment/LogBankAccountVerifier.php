<?php

namespace App\Services\Payment;

use App\Contracts\Payment\BankAccountVerifier;
use App\DataTransferObjects\Payment\VerifiedBankAccount;
use App\Exceptions\Payment\BankAccountVerificationException;

class LogBankAccountVerifier implements BankAccountVerifier
{
    public function verify(int $bankBin, string $accountNumber, string $bankCode, string $bankName): VerifiedBankAccount
    {
        if (! preg_match('/^\d{8,19}$/', $accountNumber)) {
            throw new BankAccountVerificationException(
                'Số tài khoản không hợp lệ. Vui lòng nhập từ 8 đến 19 chữ số.',
                'invalid_format',
            );
        }

        if (str_ends_with($accountNumber, '0000')) {
            throw new BankAccountVerificationException(
                'Số tài khoản không tồn tại hoặc ngân hàng không hỗ trợ tra cứu.',
                'account_invalid',
            );
        }

        return new VerifiedBankAccount(
            bankCode: $bankCode,
            bankName: $bankName,
            bankBin: $bankBin,
            accountNumber: $accountNumber,
            accountHolder: 'NGUYEN VAN A',
            provider: 'log',
            providerCode: '00',
        );
    }
}
