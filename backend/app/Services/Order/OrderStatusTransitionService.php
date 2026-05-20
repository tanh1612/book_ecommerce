<?php

namespace App\Services\Order;

use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentMethod;
use App\Enums\Order\PaymentStatus;
use App\Models\Account;
use App\Models\Order;
use App\Models\OrderTimeline;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class OrderStatusTransitionService
{
    public const TIMELINE_NOTE_PROCESS = 'Chuyển đơn sang trạng thái đang xử lý.';

    public const TIMELINE_NOTE_SHIP = 'Bắt đầu giao hàng.';

    public const TIMELINE_NOTE_COD_PAID = 'Xác nhận đã thu tiền COD.';

    public const TIMELINE_NOTE_DELIVERED = 'Xác nhận giao hàng thành công.';

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
                    'current_status' => OrderStatus::DELIVERED,
                ]);

                $this->createTimeline(
                    $locked,
                    OrderStatus::DELIVERED,
                    $actor,
                    $note,
                    self::TIMELINE_NOTE_DELIVERED,
                );

                Log::info('Order delivered by admin', [
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
