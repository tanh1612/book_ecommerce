<?php

namespace App\Enums\Review;

use Filament\Support\Contracts\HasLabel;

enum ReviewStatus: string implements HasLabel
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PENDING => 'Chờ duyệt',
            self::APPROVED => 'Đã duyệt',
            self::REJECTED => 'Bị từ chối',
        };
    }
}
