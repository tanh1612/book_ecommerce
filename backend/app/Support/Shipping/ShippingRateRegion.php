<?php

declare(strict_types=1);

namespace App\Support\Shipping;

/**
 * Khu vực tính phí vận chuyển — mã phải khớp province_code từ API địa giới (địa chỉ khách).
 */
final class ShippingRateRegion
{
    /** Mã tỉnh/TP theo API (xem LocationProxyTest: Hà Nội = 01). */
    public const HANOI = '01';

    /** TP Hồ Chí Minh — mã phổ biến trong bộ mã tỉnh; đổi nếu upstream TinhThanhPho khác. */
    public const HO_CHI_MINH = '79';

    /** Giá trị form (không lưu DB) cho “các tỉnh khác / ngoại thành”. */
    public const SELECT_OTHER = '__other__';

    /**
     * @return array<string, string>
     */
    public static function selectOptionsWith(?string $currentDbProvinceCode): array
    {
        $options = [
            self::HANOI => 'Hà Nội',
            self::HO_CHI_MINH => 'Thành phố Hồ Chí Minh',
            self::SELECT_OTHER => 'Ngoại thành',
        ];

        if (
            is_string($currentDbProvinceCode)
            && $currentDbProvinceCode !== ''
            && $currentDbProvinceCode !== self::HANOI
            && $currentDbProvinceCode !== self::HO_CHI_MINH
        ) {
            $options[$currentDbProvinceCode] = sprintf('Mã cũ (%s) — nên đổi sang một trong ba lựa chọn', $currentDbProvinceCode);
        }

        return $options;
    }

    public static function normalizeToSelect(mixed $dbProvinceCode): string
    {
        if ($dbProvinceCode === null || $dbProvinceCode === '') {
            return self::SELECT_OTHER;
        }

        return is_scalar($dbProvinceCode) ? (string) $dbProvinceCode : self::SELECT_OTHER;
    }

    public static function normalizeToDatabase(mixed $selectValue): ?string
    {
        if ($selectValue === null || $selectValue === '' || $selectValue === self::SELECT_OTHER) {
            return null;
        }

        return is_scalar($selectValue) ? (string) $selectValue : null;
    }

    public static function tableLabel(?string $dbProvinceCode): string
    {
        return match ($dbProvinceCode) {
            self::HANOI => 'Hà Nội',
            self::HO_CHI_MINH => 'Thành phố Hồ Chí Minh',
            null, '' => 'Ngoại thành',
            default => sprintf('Mã: %s', $dbProvinceCode),
        };
    }
}
