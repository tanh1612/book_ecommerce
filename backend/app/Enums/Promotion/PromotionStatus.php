<?php

namespace App\Enums\Promotion;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PromotionStatus: string implements HasColor, HasLabel
{
    case SCHEDULED = 'scheduled';
    case ACTIVE = 'active';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::SCHEDULED => 'Sắp diễn ra',
            self::ACTIVE => 'Đang diễn ra',
            self::EXPIRED => 'Đã kết thúc',
            self::CANCELLED => 'Đã hủy',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::SCHEDULED => 'info',
            self::ACTIVE => 'success',
            self::EXPIRED => 'gray',
            self::CANCELLED => 'danger',
        };
    }
}
