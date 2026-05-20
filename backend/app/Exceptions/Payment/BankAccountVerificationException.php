<?php

namespace App\Exceptions\Payment;

use Exception;
use Throwable;

class BankAccountVerificationException extends Exception
{
    public function __construct(
        string $message = 'Không thể xác minh số tài khoản ngân hàng.',
        public readonly ?string $providerCode = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
