<?php

namespace App\Enums\Order;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case COD = 'cod';
    case VIETQR = 'vietqr';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::COD => 'Thanh toán khi nhận hàng (COD)',
            self::VIETQR => 'Chuyển khoản VietQR',
        };
    }
}
