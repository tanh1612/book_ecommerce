<?php

namespace App\Console\Commands;

use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentStatus;
use App\Enums\Payment\PaymentGateway;
use App\Enums\Payment\PaymentTransactionStatus;
use App\Enums\Payment\PaymentTransactionType;
use App\Models\Order;
use App\Models\OrderTimeline;
use App\Models\PaymentTransaction;
use App\Services\Order\OrderStatusTransitionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpireManualRefundDeadlinesCommand extends Command
{
    protected $signature = 'orders:expire-manual-refunds';

    protected $description = 'Close VNPay manual-refund cases when the customer misses the info deadline.';

    public function handle(): int
    {
        $query = Order::query()
            ->where('current_status', OrderStatus::CANCELLED)
            ->where('payment_status', PaymentStatus::REFUNDING)
            ->whereNotNull('refund_deadline_at')
            ->where('refund_deadline_at', '<', now());

        $count = 0;

        foreach ($query->cursor() as $order) {
            try {
                DB::transaction(function () use ($order): void {
                    /** @var Order|null $locked */
                    $locked = Order::query()->whereKey($order->id)->lockForUpdate()->first();
                    if ($locked === null) {
                        return;
                    }

                    if (
                        $locked->current_status !== OrderStatus::CANCELLED
                        || $locked->payment_status !== PaymentStatus::REFUNDING
                        || $locked->refund_deadline_at === null
                        || $locked->refund_deadline_at->isFuture()
                    ) {
                        return;
                    }

                    PaymentTransaction::query()
                        ->where('order_id', $locked->id)
                        ->where('gateway', PaymentGateway::VNPAY)
                        ->where('type', PaymentTransactionType::REFUND)
                        ->where('status', PaymentTransactionStatus::PENDING)
                        ->update([
                            'status' => PaymentTransactionStatus::EXPIRED,
                            'completed_at' => now(),
                        ]);

                    $locked->update([
                        'current_status' => OrderStatus::REFUND_EXPIRED,
                        'payment_status' => PaymentStatus::REFUND_EXPIRED,
                        'refund_deadline_at' => null,
                    ]);

                    OrderTimeline::query()->create([
                        'order_id' => $locked->id,
                        'status' => OrderStatus::REFUND_EXPIRED->value,
                        'note' => OrderStatusTransitionService::TIMELINE_NOTE_REFUND_EXPIRED_NO_CONTACT,
                    ]);
                });
                $count++;
            } catch (\Throwable $e) {
                Log::error('Expire manual refund deadline failed', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Processed {$count} expired manual refund orders.");

        return self::SUCCESS;
    }
}
