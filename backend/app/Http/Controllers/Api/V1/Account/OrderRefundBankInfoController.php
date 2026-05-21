<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Controller;
use App\Services\Payment\RefundBankCatalogService;
use RuntimeException;
use App\Http\Requests\Account\RefundBankInfoRequest;
use App\Http\Resources\RefundBankResource;
use App\Http\Resources\RefundBankInfoSubmissionResource;
use App\Models\Order;
use App\Services\Order\OrderStatusTransitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class OrderRefundBankInfoController extends Controller
{
    public function banks(RefundBankCatalogService $bankCatalog): JsonResponse|AnonymousResourceCollection
    {
        try {
            return RefundBankResource::collection($bankCatalog->banks());
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 503);
        }
    }

    public function store(
        RefundBankInfoRequest $request,
        Order $order,
        RefundBankCatalogService $bankCatalog,
        OrderStatusTransitionService $orderStatusTransitionService,
    ): JsonResponse {
        $this->authorize('submitRefundBankInfo', $order);

        if (! $order->canSubmitRefundBankInfo()) {
            return response()->json([
                'message' => 'Không thể gửi thông tin hoàn tiền cho đơn này.',
            ], 422);
        }

        $bankCode = $request->validated('bank_code');
        $bankMeta = $this->resolveBankMetadata($bankCatalog, $bankCode);

        try {
            $updated = $orderStatusTransitionService->submitRefundBankInfo(
                $order,
                $request->user(),
                [
                    'bank_code' => $bankCode,
                    'bank_name' => $bankMeta['bank_name'],
                    'bank_bin' => $bankMeta['bank_bin'],
                    'account_number' => $request->validated('account_number'),
                    'account_holder' => $request->validated('account_holder'),
                ],
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? 'Dữ liệu không hợp lệ.',
                'errors' => $e->errors(),
            ], 422);
        }

        return (new RefundBankInfoSubmissionResource($updated))->response()->setStatusCode(201);
    }

    /**
     * @return array{bank_name: string, bank_bin: int|null}
     */
    private function resolveBankMetadata(RefundBankCatalogService $bankCatalog, string $bankCode): array
    {
        try {
            $bank = $bankCatalog->findByCode($bankCode);

            return [
                'bank_name' => $bank['short_name'],
                'bank_bin' => $bank['bin'],
            ];
        } catch (RuntimeException) {
            return [
                'bank_name' => $bankCode,
                'bank_bin' => null,
            ];
        }
    }
}
