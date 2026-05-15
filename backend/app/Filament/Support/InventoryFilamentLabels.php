<?php

namespace App\Filament\Support;

use Illuminate\Support\Str;

/**
 * Nhãn hiển thị cho tồn kho (khớp trường DB; dùng chung Resource và Relation Manager).
 */
final class InventoryFilamentLabels
{
    public static function attribute(string $attribute): string
    {
        return match ($attribute) {
            'book', 'book_id' => 'Sách',
            'warehouse', 'warehouse_id' => 'Kho',
            'quantity' => 'Số lượng tồn',
            'sold_quantity' => 'Đã bán',
            'reserved_quantity' => 'Đang giữ',
            'available_stock' => 'Có thể bán',
            'location_code' => 'Mã vị trí',
            'last_restocked_at' => 'Nhập kho gần nhất',
            'stock_status' => 'Trạng thái tồn',
            default => Str::headline(str_replace('_', ' ', $attribute)),
        };
    }
}
