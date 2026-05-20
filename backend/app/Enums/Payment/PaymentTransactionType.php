<?php

namespace App\Enums\Payment;

enum PaymentTransactionType: string
{
    case PAYMENT = 'payment';
    case REFUND = 'refund';
}
