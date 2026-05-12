<?php

namespace App\Enums\Order;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentStatus: string implements HasColor, HasLabel
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case FAILED = 'failed';
    case REFUNDING = 'refunding';
    case REFUNDED = 'refunded';
    case REFUND_EXPIRED = 'refund_expired';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PENDING => 'Chờ thanh toán',
            self::PAID => 'Đã thanh toán',
            self::FAILED => 'Thanh toán lỗi',
            self::REFUNDING => 'Chờ hoàn tiền',
            self::REFUNDED => 'Đã hoàn trả tiền',
            self::REFUND_EXPIRED => 'Quá hạn hoàn tiền',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::PAID => 'success',
            self::FAILED => 'danger',
            self::REFUNDING => 'info',
            self::REFUNDED => 'primary',
            self::REFUND_EXPIRED => 'danger',
        };
    }
}
