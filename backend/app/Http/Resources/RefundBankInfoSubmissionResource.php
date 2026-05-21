<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Order $resource
 */
class RefundBankInfoSubmissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Order $order */
        $order = $this->resource;
        $bankInfo = $order->submittedRefundBankInfo();

        return [
            'order_id' => $order->id,
            'payment_status' => $order->payment_status?->value,
            'current_status' => $order->current_status?->value,
            'manual_refund' => [
                'refund_amount' => (float) $order->final_amount,
                'support_hotline' => (string) config('refund.support_hotline', ''),
                'provide_info_deadline_at' => $order->refund_deadline_at?->toIso8601String(),
                'needs_bank_info' => $order->canSubmitRefundBankInfo(),
                'bank_info' => $bankInfo !== null ? $this->formatBankInfoForCustomer($bankInfo) : null,
            ],
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
