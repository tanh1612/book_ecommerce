<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Http\Controllers\Controller;
use App\Services\Payment\VnPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VnPayIpnController extends Controller
{
    public function __invoke(Request $request, VnPayService $vnPay): JsonResponse
    {
        try {
            return response()->json($vnPay->handleIpn($request->query()));
        } catch (\Throwable $e) {
            Log::error('VNPay IPN controller failure', [
                'vnp_TxnRef' => $request->query('vnp_TxnRef'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'RspCode' => '99',
                'Message' => 'Unknown error',
            ]);
        }
    }
}
