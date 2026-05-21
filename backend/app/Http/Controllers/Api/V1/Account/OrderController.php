<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Controller;
use App\Http\Resources\AccountOrderResource;
use App\Models\Order;
use App\Services\Order\OrderStatusTransitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
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
}
