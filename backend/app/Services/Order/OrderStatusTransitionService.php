<?php

namespace App\Services\Order;

use App\DataTransferObjects\Payment\VerifiedBankAccount;
use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentMethod;
use App\Enums\Order\PaymentStatus;
use App\Enums\Payment\PaymentGateway;
use App\Enums\Payment\PaymentTransactionStatus;
use App\Enums\Payment\PaymentTransactionType;
use App\Models\Account;
use App\Models\Order;
use App\Models\OrderTimeline;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class OrderStatusTransitionService
{
    public const TIMELINE_NOTE_PROCESS = 'Chuyển đơn sang trạng thái đang xử lý.';

    public const TIMELINE_NOTE_SHIP = 'Bắt đầu giao hàng.';

    public const TIMELINE_NOTE_COD_PAID = 'Xác nhận đã thu tiền COD.';

    public const TIMELINE_NOTE_COMPLETED = 'Đơn hàng đã hoàn tất.';

    public static function timelineNoteCodPaid(Order $order): string
    {
        $amount = number_format((float) $order->final_amount, 0, ',', '.');

        return sprintf('%s Tổng tiền: %s đ.', self::TIMELINE_NOTE_COD_PAID, $amount);
    }

    public const TIMELINE_NOTE_CANCEL = 'Admin hủy đơn hàng.';

    public const TIMELINE_NOTE_CHECKOUT_COD = 'Đơn thanh toán COD đã xác nhận khi đặt hàng.';

    public const TIMELINE_NOTE_CHECKOUT_VNPAY_PENDING = 'Đơn được tạo khi đặt hàng, chờ thanh toán VNPay.';

    public const TIMELINE_NOTE_VNPAY_PAID = 'Thanh toán VNPay thành công.';

    public const TIMELINE_NOTE_VNPAY_FAILED = 'Thanh toán VNPay thất bại.';

    public const TIMELINE_NOTE_VNPAY_EXPIRED = 'Hết hạn thời gian thanh toán VNPay, đơn đã hủy.';

    public const TIMELINE_NOTE_DELIVERY_FAILED_COD = 'Giao hàng thất bại — đơn COD chưa thu tiền, đã hủy và giải phóng tồn kho.';

    public const TIMELINE_NOTE_DELIVERY_FAILED_SHORT = 'Giao hàng thất bại.';

    public const TIMELINE_NOTE_DELIVERY_FAILED_VNPAY_MANUAL_REFUND = 'Giao hàng thất bại — chờ hoàn tiền thủ công (VNPay). Khách vui lòng liên hệ hotline để cung cấp thông tin hoàn tiền trước hạn.';

    public const TIMELINE_NOTE_MANUAL_REFUND_CONFIRMED = 'Admin xác nhận đã hoàn tiền thủ công.';

    public const TIMELINE_NOTE_REFUND_BANK_INFO_SUBMITTED = 'Khách đã cung cấp thông tin hoàn tiền, tài khoản đã được xác minh.';

    public const TIMELINE_NOTE_REFUND_EXPIRED_NO_CONTACT = 'Đóng - Không hoàn tiền do quá hạn cung cấp thông tin.';

    public function __construct(
        private OrderInventoryService $orderInventory,
    ) {}

    public function processOrder(Order $order, Account $actor, ?string $note = null): Order
    {
        return $this->transition(
            $order,
            $actor,
            expectedStatus: OrderStatus::CONFIRMED,
            newStatus: OrderStatus::PROCESSING,
            defaultNote: self::TIMELINE_NOTE_PROCESS,
            note: $note,
            logEvent: 'Order moved to processing by admin',
        );
    }

    public function shipOrder(Order $order, Account $actor, ?string $note = null): Order
    {
        return $this->transition(
            $order,
            $actor,
            expectedStatus: OrderStatus::PROCESSING,
            newStatus: OrderStatus::SHIPPING,
            defaultNote: self::TIMELINE_NOTE_SHIP,
            note: $note,
            logEvent: 'Order moved to shipping by admin',
        );
    }

    public function confirmCodPayment(Order $order, Account $actor, ?string $note = null): Order
    {
        try {
            return DB::transaction(function () use ($order, $actor, $note): Order {
                $locked = $this->lockOrder($order);

                if ($locked->current_status !== OrderStatus::SHIPPING) {
                    throw ValidationException::withMessages([
                        'current_status' => [
                            'Chỉ xác nhận thu tiền khi đơn đang ở trạng thái Đang giao hàng.',
                        ],
                    ]);
                }

                if ($locked->payment_method !== PaymentMethod::COD) {
                    throw ValidationException::withMessages([
                        'payment_method' => [
                            'Chỉ áp dụng xác nhận thu tiền cho đơn thanh toán COD.',
                        ],
                    ]);
                }

                if ($locked->payment_status !== PaymentStatus::PENDING) {
                    throw ValidationException::withMessages([
                        'payment_status' => [
                            'Đơn không ở trạng thái chờ thanh toán.',
                        ],
                    ]);
                }

                $locked->update([
                    'payment_status' => PaymentStatus::PAID,
                ]);

                $this->createTimeline(
                    $locked,
                    OrderStatus::SHIPPING,
                    $actor,
                    $note,
                    self::timelineNoteCodPaid($locked),
                );

                Log::info('COD payment confirmed by admin', [
                    'order_id' => $locked->id,
                    'actor_id' => $actor->id,
                ]);

                return $locked->fresh();
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Admin COD payment confirmation failed', [
                'order_id' => $order->id,
                'actor_id' => $actor->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function deliverOrder(Order $order, Account $actor, ?string $note = null): Order
    {
        try {
            return DB::transaction(function () use ($order, $actor, $note): Order {
                $locked = $this->lockOrder($order);

                if ($locked->current_status !== OrderStatus::SHIPPING) {
                    throw ValidationException::withMessages([
                        'current_status' => [
                            'Chỉ xác nhận giao hàng khi đơn đang ở trạng thái Đang giao hàng.',
                        ],
                    ]);
                }

                if ($locked->payment_status !== PaymentStatus::PAID) {
                    throw ValidationException::withMessages([
                        'payment_status' => [
                            'Đơn chưa được xác nhận thanh toán. Với COD, hãy xác nhận đã thu tiền trước.',
                        ],
                    ]);
                }

                $this->orderInventory->fulfillDeliveredOrder($locked);

                $locked->update([
                    'current_status' => OrderStatus::COMPLETED,
                ]);

                $this->createTimeline(
                    $locked,
                    OrderStatus::COMPLETED,
                    $actor,
                    $note,
                    self::TIMELINE_NOTE_COMPLETED,
                );

                Log::info('Order completed by admin', [
                    'order_id' => $locked->id,
                    'actor_id' => $actor->id,
                ]);

                return $locked->fresh();
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Admin order delivery confirmation failed', [
                'order_id' => $order->id,
                'actor_id' => $actor->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function markDeliveryFailed(Order $order, Account $actor, ?string $note = null): Order
    {
        try {
            return DB::transaction(function () use ($order, $actor, $note): Order {
                $locked = $this->lockOrder($order);

                if ($locked->current_status !== OrderStatus::SHIPPING) {
                    throw ValidationException::withMessages([
                        'current_status' => [
                            'Chỉ đánh dấu giao hàng thất bại khi đơn đang ở trạng thái Đang giao hàng.',
                        ],
                    ]);
                }

                if ($locked->payment_method === PaymentMethod::COD) {
                    if ($locked->payment_status !== PaymentStatus::PENDING) {
                        throw ValidationException::withMessages([
                            'payment_status' => [
                                'Đơn COD đã thu tiền không được xử lý qua thao tác này.',
                            ],
                        ]);
                    }

                    $this->orderInventory->releaseReservedForOrder($locked);

                    $locked->update([
                        'current_status' => OrderStatus::CANCELLED,
                        'payment_status' => PaymentStatus::FAILED,
                    ]);

                    $this->createTimeline(
                        $locked,
                        OrderStatus::CANCELLED,
                        $actor,
                        $note,
                        self::TIMELINE_NOTE_DELIVERY_FAILED_COD,
                    );

                    Log::info('COD delivery failed — order cancelled without payment', [
                        'order_id' => $locked->id,
                        'actor_id' => $actor->id,
                    ]);

                    return $locked->fresh();
                }

                if ($locked->payment_method === PaymentMethod::VNPAY) {
                    if ($locked->payment_status !== PaymentStatus::PAID) {
                        throw ValidationException::withMessages([
                            'payment_status' => [
                                'Chỉ tạo hoàn tiền thủ công khi đơn VNPay đã thanh toán.',
                            ],
                        ]);
                    }

                    $duplicatePendingRefund = PaymentTransaction::query()
                        ->where('order_id', $locked->id)
                        ->where('type', PaymentTransactionType::REFUND)
                        ->where('status', PaymentTransactionStatus::PENDING)
                        ->exists();

                    if ($duplicatePendingRefund) {
                        throw ValidationException::withMessages([
                            'payment' => ['Đơn đã có giao dịch hoàn tiền đang chờ xử lý.'],
                        ]);
                    }

                    $this->orderInventory->releaseReservedForOrder($locked);

                    $deadlineDays = max(1, (int) config('refund.manual_refund_deadline_days', 15));
                    $deadline = now()->addDays($deadlineDays);

                    $locked->update([
                        'current_status' => OrderStatus::CANCELLED,
                        'payment_status' => PaymentStatus::REFUNDING,
                        'refund_deadline_at' => $deadline,
                    ]);

                    $hotline = (string) config('refund.support_hotline', '');

                    PaymentTransaction::query()->create([
                        'order_id' => $locked->id,
                        'gateway' => PaymentGateway::VNPAY,
                        'gateway_txn_id' => null,
                        'type' => PaymentTransactionType::REFUND,
                        'amount' => $locked->final_amount,
                        'status' => PaymentTransactionStatus::PENDING,
                        'payload' => [
                            'support_hotline' => $hotline,
                            'delivery_failed_at' => now()->toIso8601String(),
                            'refund_deadline_at' => $deadline->toIso8601String(),
                        ],
                    ]);

                    $this->createTimeline(
                        $locked,
                        OrderStatus::CANCELLED,
                        $actor,
                        $note,
                        self::TIMELINE_NOTE_DELIVERY_FAILED_VNPAY_MANUAL_REFUND,
                    );

                    Log::info('VNPay delivery failed — manual refund pending', [
                        'order_id' => $locked->id,
                        'actor_id' => $actor->id,
                        'refund_deadline_at' => $deadline->toIso8601String(),
                    ]);

                    return $locked->fresh();
                }

                throw ValidationException::withMessages([
                    'payment_method' => ['Phương thức thanh toán không hỗ trợ thao tác giao hàng thất bại.'],
                ]);
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Mark delivery failed failed', [
                'order_id' => $order->id,
                'actor_id' => $actor->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function submitRefundBankInfo(
        Order $order,
        Account $account,
        VerifiedBankAccount $verifiedBankAccount,
    ): Order {
        try {
            return DB::transaction(function () use ($order, $account, $verifiedBankAccount): Order {
                $locked = $this->lockOrder($order);

                if ($locked->account_id !== $account->id) {
                    throw ValidationException::withMessages([
                        'order' => ['Bạn không có quyền gửi thông tin hoàn tiền cho đơn này.'],
                    ]);
                }

                if ($locked->payment_status !== PaymentStatus::REFUNDING) {
                    throw ValidationException::withMessages([
                        'payment_status' => ['Đơn không ở trạng thái chờ hoàn tiền.'],
                    ]);
                }

                if ($locked->refund_deadline_at !== null && $locked->refund_deadline_at->isPast()) {
                    throw ValidationException::withMessages([
                        'refund_deadline_at' => ['Đã quá hạn cung cấp thông tin hoàn tiền.'],
                    ]);
                }

                /** @var PaymentTransaction|null $refundTxn */
                $refundTxn = PaymentTransaction::query()
                    ->where('order_id', $locked->id)
                    ->where('type', PaymentTransactionType::REFUND)
                    ->where('status', PaymentTransactionStatus::PENDING)
                    ->lockForUpdate()
                    ->first();

                if ($refundTxn === null) {
                    throw ValidationException::withMessages([
                        'payment' => ['Không tìm thấy giao dịch hoàn tiền đang chờ.'],
                    ]);
                }

                $payload = $refundTxn->payload ?? [];

                if (isset($payload['bank_info']) && is_array($payload['bank_info'])) {
                    throw ValidationException::withMessages([
                        'bank_info' => ['Thông tin hoàn tiền đã được gửi trước đó.'],
                    ]);
                }

                $payload['bank_info'] = $verifiedBankAccount->toBankInfoPayload($account->id);

                $refundTxn->update([
                    'payload' => $payload,
                ]);

                $maskedAccount = $this->maskAccountNumber($verifiedBankAccount->accountNumber);
                $timelineNote = sprintf(
                    '%s %s •••%s.',
                    self::TIMELINE_NOTE_REFUND_BANK_INFO_SUBMITTED,
                    $verifiedBankAccount->bankName,
                    $maskedAccount,
                );

                OrderTimeline::query()->create([
                    'order_id' => $locked->id,
                    'status' => OrderStatus::CANCELLED->value,
                    'note' => $timelineNote,
                    'actor' => $account->email,
                ]);

                Log::info('Refund bank info submitted by customer', [
                    'order_id' => $locked->id,
                    'account_id' => $account->id,
                    'bank_code' => $verifiedBankAccount->bankCode,
                ]);

                return $locked->fresh();
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Submit refund bank info failed', [
                'order_id' => $order->id,
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function confirmManualRefundCompleted(
        Order $order,
        Account $actor,
        string $referenceCode,
        string $transferredAt,
        ?string $note = null,
    ): Order {
        try {
            return DB::transaction(function () use ($order, $actor, $referenceCode, $transferredAt, $note): Order {
                $locked = $this->lockOrder($order);

                if ($locked->current_status !== OrderStatus::CANCELLED) {
                    throw ValidationException::withMessages([
                        'current_status' => [
                            'Chỉ xác nhận hoàn tiền khi đơn đã hủy sau giao hàng thất bại.',
                        ],
                    ]);
                }

                if ($locked->payment_status !== PaymentStatus::REFUNDING) {
                    throw ValidationException::withMessages([
                        'payment_status' => [
                            'Đơn không ở trạng thái chờ hoàn tiền thủ công.',
                        ],
                    ]);
                }

                /** @var PaymentTransaction|null $refundTxn */
                $refundTxn = PaymentTransaction::query()
                    ->where('order_id', $locked->id)
                    ->where('type', PaymentTransactionType::REFUND)
                    ->where('status', PaymentTransactionStatus::PENDING)
                    ->lockForUpdate()
                    ->first();

                if ($refundTxn === null) {
                    throw ValidationException::withMessages([
                        'payment' => ['Không tìm thấy giao dịch hoàn tiền đang chờ.'],
                    ]);
                }

                $payload = $refundTxn->payload ?? [];
                $bankInfo = $payload['bank_info'] ?? null;

                if (! is_array($bankInfo) || ($bankInfo['verification']['status'] ?? null) !== 'verified') {
                    throw ValidationException::withMessages([
                        'bank_info' => ['Khách chưa cung cấp thông tin hoàn tiền đã xác minh.'],
                    ]);
                }

                $transferDate = \Illuminate\Support\Carbon::parse($transferredAt);
                if ($transferDate->isFuture()) {
                    throw ValidationException::withMessages([
                        'transferred_at' => ['Ngày chuyển khoản không được ở tương lai.'],
                    ]);
                }

                $payload['transfer_confirmation'] = [
                    'reference_code' => $referenceCode,
                    'transferred_at' => $transferredAt,
                    'confirmed_amount' => (float) $locked->final_amount,
                    'confirmed_at' => now()->toIso8601String(),
                    'confirmed_by' => $actor->email,
                ];
                $trimNote = trim((string) $note);
                if ($trimNote !== '') {
                    $payload['transfer_confirmation']['admin_note'] = $trimNote;
                }

                $refundTxn->update([
                    'status' => PaymentTransactionStatus::REFUNDED,
                    'completed_at' => now(),
                    'payload' => $payload,
                ]);

                $locked->update([
                    'payment_status' => PaymentStatus::REFUNDED,
                    'refund_deadline_at' => null,
                ]);

                $defaultTimelineNote = sprintf(
                    '%s Mã chứng từ: %s.',
                    self::TIMELINE_NOTE_MANUAL_REFUND_CONFIRMED,
                    $referenceCode,
                );
                if ($trimNote !== '') {
                    $defaultTimelineNote .= ' Ghi chú: '.$trimNote;
                }

                $this->createTimeline(
                    $locked,
                    OrderStatus::CANCELLED,
                    $actor,
                    null,
                    $defaultTimelineNote,
                );

                Log::info('Manual refund confirmed by admin', [
                    'order_id' => $locked->id,
                    'actor_id' => $actor->id,
                    'payment_transaction_id' => $refundTxn->id,
                ]);

                return $locked->fresh();
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Confirm manual refund failed', [
                'order_id' => $order->id,
                'actor_id' => $actor->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function cancelConfirmedOrder(Order $order, Account $actor, ?string $note = null): Order
    {
        try {
            return DB::transaction(function () use ($order, $actor, $note): Order {
                $locked = $this->lockOrder($order);

                if ($locked->current_status !== OrderStatus::CONFIRMED) {
                    throw ValidationException::withMessages([
                        'current_status' => [
                            'Chỉ được hủy đơn đang ở trạng thái Đã xác nhận (chưa chuyển sang Đang xử lý).',
                        ],
                    ]);
                }

                $this->orderInventory->releaseReservedForOrder($locked);

                $locked->update([
                    'current_status' => OrderStatus::CANCELLED,
                ]);

                $this->createTimeline(
                    $locked,
                    OrderStatus::CANCELLED,
                    $actor,
                    $note,
                    self::TIMELINE_NOTE_CANCEL,
                );

                Log::info('Order cancelled by admin', [
                    'order_id' => $locked->id,
                    'actor_id' => $actor->id,
                ]);

                return $locked->fresh();
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Admin order cancel failed', [
                'order_id' => $order->id,
                'actor_id' => $actor->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function transition(
        Order $order,
        Account $actor,
        OrderStatus $expectedStatus,
        OrderStatus $newStatus,
        string $defaultNote,
        ?string $note,
        string $logEvent,
    ): Order {
        try {
            return DB::transaction(function () use (
                $order,
                $actor,
                $expectedStatus,
                $newStatus,
                $defaultNote,
                $note,
                $logEvent,
            ): Order {
                $locked = $this->lockOrder($order);

                if ($locked->current_status !== $expectedStatus) {
                    throw ValidationException::withMessages([
                        'current_status' => [
                            "Chỉ thực hiện khi đơn đang ở trạng thái {$expectedStatus->getLabel()}.",
                        ],
                    ]);
                }

                $locked->update([
                    'current_status' => $newStatus,
                ]);

                $this->createTimeline($locked, $newStatus, $actor, $note, $defaultNote);

                Log::info($logEvent, [
                    'order_id' => $locked->id,
                    'actor_id' => $actor->id,
                    'from' => $expectedStatus->value,
                    'to' => $newStatus->value,
                ]);

                return $locked->fresh();
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Admin order status transition failed', [
                'order_id' => $order->id,
                'actor_id' => $actor->id,
                'expected_status' => $expectedStatus->value,
                'new_status' => $newStatus->value,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function maskAccountNumber(string $accountNumber): string
    {
        $length = strlen($accountNumber);

        if ($length <= 4) {
            return $accountNumber;
        }

        return substr($accountNumber, -4);
    }

    private function lockOrder(Order $order): Order
    {
        /** @var Order|null $locked */
        $locked = Order::query()->whereKey($order->id)->lockForUpdate()->first();

        if ($locked === null) {
            throw ValidationException::withMessages([
                'order' => ['Không tìm thấy đơn hàng.'],
            ]);
        }

        return $locked;
    }

    private function createTimeline(
        Order $order,
        OrderStatus $status,
        Account $actor,
        ?string $note,
        string $defaultNote,
    ): void {
        $noteText = trim((string) $note);

        OrderTimeline::query()->create([
            'order_id' => $order->id,
            'status' => $status->value,
            'note' => $noteText !== '' ? $noteText : $defaultNote,
            'actor' => $actor->email,
        ]);
    }
}
