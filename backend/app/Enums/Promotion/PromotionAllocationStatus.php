<?php

namespace App\Enums\Promotion;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PromotionAllocationStatus: string implements HasColor, HasLabel
{
    case RESERVED = 'reserved';
    case CONFIRMED = 'confirmed';
    case RELEASED = 'released';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::RESERVED => 'Đang giữ suất',
            self::CONFIRMED => 'Đã xác nhận',
            self::RELEASED => 'Đã hoàn trả',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::RESERVED => 'warning',
            self::CONFIRMED => 'success',
            self::RELEASED => 'gray',
        };
    }
}
