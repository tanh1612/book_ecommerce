<?php

namespace App\Exceptions\Promotion;

use Exception;
use Illuminate\Http\JsonResponse;

class PromotionUnavailableException extends Exception
{
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => 'Khuyến mãi không còn áp dụng. Vui lòng kiểm tra lại giá.',
            'code' => 'PROMOTION_UNAVAILABLE',
            'errors' => [
                'promotion' => [
                    'Khuyến mãi đã hết hoặc giá sản phẩm đã thay đổi.',
                ],
            ],
        ], 422);
    }
}
