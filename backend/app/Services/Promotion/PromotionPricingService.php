<?php

namespace App\Services\Promotion;

class PromotionPricingService
{
    /**
     * @return array{unit_price: string, line_subtotal: string, discount_amount: string, line_total: string}
     */
    public function calculateLine(string $unitPrice, int $quantity, ?string $discountPercent = null): array
    {
        $lineSubtotal = bcmul($unitPrice, (string) $quantity, 2);
        $discountAmount = '0.00';

        if ($discountPercent !== null && bccomp($discountPercent, '0', 2) === 1) {
            $discountAmount = bcdiv(bcmul($lineSubtotal, $discountPercent, 4), '100', 2);

            if (bccomp($discountAmount, $lineSubtotal, 2) === 1) {
                $discountAmount = $lineSubtotal;
            }
        }

        return [
            'unit_price' => $unitPrice,
            'line_subtotal' => $lineSubtotal,
            'discount_amount' => $discountAmount,
            'line_total' => bcsub($lineSubtotal, $discountAmount, 2),
        ];
    }
}
