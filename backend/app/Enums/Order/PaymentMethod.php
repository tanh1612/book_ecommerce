<?php

namespace App\Enums\Order;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case COD = 'cod';
    case VNPAY = 'vnpay';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::COD => 'Thanh toán khi nhận hàng (COD)',
            self::VNPAY => 'Thanh toán VNPay',
        };
    }
}
