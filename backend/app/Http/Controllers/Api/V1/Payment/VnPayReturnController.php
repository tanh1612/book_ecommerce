<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Http\Controllers\Controller;
use App\Services\Payment\VnPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VnPayReturnController extends Controller
{
    public function __invoke(Request $request, VnPayService $vnPay): JsonResponse
    {
        try {
            $result = $vnPay->handleReturn($request->query());

            $status = ($result['success'] ?? false) ? 200 : 422;

            return response()->json($result, $status);
        } catch (\Throwable $e) {
            Log::error('VNPay return controller failure', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to process VNPay return.',
            ], 500);
        }
    }
}
