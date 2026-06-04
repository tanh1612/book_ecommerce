<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Http\Controllers\Controller;
use App\Services\Payment\VnPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VnPayReturnController extends Controller
{
    public function __invoke(Request $request, VnPayService $vnPay): JsonResponse|RedirectResponse
    {
        try {
            $result = $vnPay->handleReturn($request->query());

            if (! $request->expectsJson() && $request->query('format') !== 'json') {
                return redirect()->away($this->frontendReturnUrl($result));
            }

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

    /**
     * @param  array<string, mixed>  $result
     */
    private function frontendReturnUrl(array $result): string
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', 'http://localhost:5173'), '/');
        $status = ($result['success'] ?? false) ? 'paid' : 'failed';

        $query = [
            'status' => $status,
        ];

        if (isset($result['order_id']) && is_scalar($result['order_id'])) {
            $query['order_id'] = (string) $result['order_id'];
        }

        if (isset($result['message']) && is_scalar($result['message'])) {
            $query['message'] = Str::limit((string) $result['message'], 120, '');
        }

        return $frontendUrl.'/payment-result?'.http_build_query($query);
    }
}
