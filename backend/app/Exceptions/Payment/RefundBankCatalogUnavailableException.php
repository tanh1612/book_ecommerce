<?php

namespace App\Exceptions\Payment;

use Exception;

class RefundBankCatalogUnavailableException extends Exception
{
    public function __construct(
        string $message = 'Không thể tải danh sách ngân hàng. Vui lòng thử lại sau.',
    ) {
        parent::__construct($message);
    }
}
