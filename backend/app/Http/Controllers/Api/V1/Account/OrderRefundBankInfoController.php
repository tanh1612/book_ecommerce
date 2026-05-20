<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Exceptions\Payment\BankAccountVerificationException;
use App\Exceptions\Payment\RefundBankCatalogUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\RefundBankInfoRequest;
use App\Http\Resources\AccountOrderResource;
use App\Http\Resources\RefundBankResource;
use App\Http\Resources\VerifiedBankAccountResource;
use App\Models\Order;
use App\Services\Order\OrderStatusTransitionService;
use App\Services\Payment\BankAccountVerificationService;
use App\Services\Payment\RefundBankCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class OrderRefundBankInfoController extends Controller
{
    public function banks(RefundBankCatalogService $bankCatalog): JsonResponse|AnonymousResourceCollection
    {
        try {
            return RefundBankResource::collection($bankCatalog->banks());
        } catch (RefundBankCatalogUnavailableException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 503);
        }
    }

    public function verify(
        RefundBankInfoRequest $request,
        Order $order,
        BankAccountVerificationService $verificationService,
    ): JsonResponse|VerifiedBankAccountResource {
        $this->authorize('submitRefundBankInfo', $order);

        if (! $order->canSubmitRefundBankInfo()) {
            return response()->json([
                'message' => 'Không thể xác minh thông tin hoàn tiền cho đơn này.',
            ], 422);
        }

        try {
            $verified = $verificationService->verify(
                $request->validated('bank_code'),
                $request->validated('account_number'),
            );
        } catch (RefundBankCatalogUnavailableException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 503);
        } catch (BankAccountVerificationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'provider_code' => $e->providerCode,
            ], 422);
        }

        return (new VerifiedBankAccountResource($verified))->response();
    }

    public function store(
        RefundBankInfoRequest $request,
        Order $order,
        BankAccountVerificationService $verificationService,
        OrderStatusTransitionService $orderStatusTransitionService,
    ): JsonResponse|AccountOrderResource {
        $this->authorize('submitRefundBankInfo', $order);

        if (! $order->canSubmitRefundBankInfo()) {
            return response()->json([
                'message' => 'Không thể gửi thông tin hoàn tiền cho đơn này.',
            ], 422);
        }

        try {
            $verified = $verificationService->verify(
                $request->validated('bank_code'),
                $request->validated('account_number'),
            );

            $updated = $orderStatusTransitionService->submitRefundBankInfo(
                $order,
                $request->user(),
                $verified,
            );
        } catch (RefundBankCatalogUnavailableException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 503);
        } catch (BankAccountVerificationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'provider_code' => $e->providerCode,
            ], 422);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? 'Dữ liệu không hợp lệ.',
                'errors' => $e->errors(),
            ], 422);
        }

        $updated->load('items');

        return (new AccountOrderResource($updated))->response()->setStatusCode(201);
    }
}
