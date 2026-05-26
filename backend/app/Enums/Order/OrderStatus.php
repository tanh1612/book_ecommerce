<?php

namespace App\Enums\Order;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasColor, HasLabel
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case PROCESSING = 'processing';
    case SHIPPING = 'shipping';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case REFUND_EXPIRED = 'refund_expired';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PENDING => 'Chờ xử lý',
            self::CONFIRMED => 'Đã xác nhận',
            self::PROCESSING => 'Đang xử lý',
            self::SHIPPING => 'Đang giao hàng',
            self::COMPLETED => 'Hoàn tất',
            self::CANCELLED => 'Đã hủy',
            self::REFUND_EXPIRED => 'Quá hạn hoàn tiền',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::CONFIRMED => 'info',
            self::PROCESSING => 'primary',
            self::SHIPPING => 'gray',
            self::COMPLETED => 'success',
            self::CANCELLED => 'danger',
            self::REFUND_EXPIRED => 'gray',
        };
    }
}
