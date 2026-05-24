<?php

namespace App\Services\Statistics;

final readonly class RevenueSummary
{
    public function __construct(
        public float $totalRevenue,
        public int $orderCount,
        public float $averageOrderValue,
        public float $totalShippingFee,
    ) {}

    public static function empty(): self
    {
        return new self(0, 0, 0, 0);
    }
}
