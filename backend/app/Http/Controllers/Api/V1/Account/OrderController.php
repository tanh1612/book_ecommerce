<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ListOrdersRequest;
use App\Http\Resources\AccountOrderResource;
use App\Http\Resources\AccountOrderSummaryResource;
use App\Models\Order;
use App\Services\Order\OrderStatusTransitionService;
use App\Services\Payment\VnPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

class OrderController extends Controller
{
    public function index(ListOrdersRequest $request): AnonymousResourceCollection
    {
        $account = $request->user();
        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 15);
        $status = $validated['status'] ?? null;

        $orders = Order::query()
            ->where('account_id', $account->id)
            ->when($status !== null, fn ($query) => $query->where('current_status', $status))
            ->with([
                'items.book.images',
            ])
            ->latest('created_at')
            ->paginate($perPage);

        return AccountOrderSummaryResource::collection($orders);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        $order->load('items');

        return (new AccountOrderResource($order))->response();
    }

    public function cancel(Request $request, Order $order, OrderStatusTransitionService $transitions): JsonResponse
    {
        $this->authorize('view', $order);

        $account = $request->user();
        $updated = $transitions->cancelByCustomer($order, $account);
        $updated->load('items');

        return (new AccountOrderResource($updated))->response();
    }

    public function vnpayPaymentUrl(Request $request, Order $order, VnPayService $vnPayService): JsonResponse
    {
        $this->authorize('view', $order);

        $clientIp = $request->ip() ?? '127.0.0.1';

        try {
            $payment = $vnPayService->createPaymentUrl($order, $clientIp, forceNew: true);
        } catch (ValidationException $e) {
            throw $e;
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => 'Không thể tạo liên kết thanh toán VNPay.',
            ], 503);
        }

        return response()->json([
            'data' => [
                'order_id' => $order->id,
                'payment_url' => $payment['payment_url'],
                'payment_transaction_id' => $payment['payment_transaction_id'],
                'vnp_TxnRef' => $payment['vnp_TxnRef'],
                'expires_at' => $payment['expires_at'],
            ],
        ]);
    }
}
