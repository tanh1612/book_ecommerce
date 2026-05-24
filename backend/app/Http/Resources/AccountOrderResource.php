<?php

namespace App\Http\Resources;

use App\Enums\Order\PaymentStatus;
use App\Models\Order;
use App\Services\Order\OrderStatusTransitionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Order $resource
 */
class AccountOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Order $order */
        $order = $this->resource;
        $items = $order->relationLoaded('items') ? $order->items : collect();
        $bankInfo = $order->submittedRefundBankInfo();

        $cancelEligibility = app(OrderStatusTransitionService::class)->customerCancelEligibility($order);

        $manualRefund = null;
        if ($order->payment_status === PaymentStatus::REFUNDING) {
            $manualRefund = [
                'status_label' => PaymentStatus::REFUNDING->getLabel(),
                'refund_amount' => (float) $order->final_amount,
                'provide_info_deadline_at' => $order->refund_deadline_at?->toIso8601String(),
                'needs_bank_info' => $order->canSubmitRefundBankInfo(),
                'bank_info' => $bankInfo !== null ? $this->formatBankInfoForCustomer($bankInfo) : null,
            ];
        }

        return [
            'id' => $order->id,
            'total_amount' => (float) $order->total_amount,
            'shipping_fee' => (float) $order->shipping_fee,
            'final_amount' => (float) $order->final_amount,
            'shipping_name' => $order->shipping_name,
            'shipping_phone' => $order->shipping_phone,
            'shipping_address' => $order->shipping_address,
            'payment_method' => $order->payment_method?->value,
            'payment_status' => $order->payment_status?->value,
            'payment_expires_at' => $order->payment_expires_at?->toIso8601String(),
            'can_pay' => $order->canPay(),
            'current_status' => $order->current_status?->value,
            'can_cancel' => $cancelEligibility['can_cancel'],
            'cancel_block_reason' => $cancelEligibility['cancel_block_reason'],
            'refund_deadline_at' => $order->refund_deadline_at?->toIso8601String(),
            'manual_refund' => $manualRefund,
            'items' => $items->map(function ($item): array {
                return [
                    'id' => $item->id,
                    'book_id' => $item->book_id,
                    'price' => (float) $item->price,
                    'quantity' => (int) $item->quantity,
                    'total_price' => (float) $item->total_price,
                    'discount_amount' => $item->discount_amount !== null ? (float) $item->discount_amount : null,
                ];
            })->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $bankInfo
     * @return array<string, mixed>
     */
    private function formatBankInfoForCustomer(array $bankInfo): array
    {
        $accountNumber = (string) ($bankInfo['account_number'] ?? '');

        return [
            'bank_code' => $bankInfo['bank_code'] ?? null,
            'bank_name' => $bankInfo['bank_name'] ?? null,
            'account_number_masked' => $this->maskAccountNumber($accountNumber),
            'account_holder' => $bankInfo['account_holder'] ?? null,
            'submitted_at' => $bankInfo['submitted_at'] ?? null,
        ];
    }

    private function maskAccountNumber(string $accountNumber): string
    {
        $length = strlen($accountNumber);

        if ($length <= 4) {
            return $accountNumber;
        }

        return str_repeat('*', max(0, $length - 4)).substr($accountNumber, -4);
    }
}
