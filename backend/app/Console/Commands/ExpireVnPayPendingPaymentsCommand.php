<?php

namespace App\Console\Commands;

use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentMethod;
use App\Enums\Order\PaymentStatus;
use App\Enums\Payment\PaymentGateway;
use App\Enums\Payment\PaymentTransactionStatus;
use App\Models\Order;
use App\Models\OrderTimeline;
use App\Models\PaymentTransaction;
use App\Services\Order\OrderInventoryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpireVnPayPendingPaymentsCommand extends Command
{
    protected $signature = 'payments:expire-vnpay';

    protected $description = 'Cancel unpaid VNPay orders past payment_expires_at and mark related transactions expired.';

    public function __construct(
        private OrderInventoryService $orderInventory,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $query = Order::query()
            ->where('payment_method', PaymentMethod::VNPAY)
            ->where('payment_status', PaymentStatus::PENDING)
            ->where('current_status', OrderStatus::PENDING)
            ->whereNotNull('payment_expires_at')
            ->where('payment_expires_at', '<', now());

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
                        $locked->payment_method !== PaymentMethod::VNPAY
                        || $locked->payment_status !== PaymentStatus::PENDING
                        || $locked->current_status !== OrderStatus::PENDING
                        || $locked->payment_expires_at === null
                        || $locked->payment_expires_at->isFuture()
                    ) {
                        return;
                    }

                    PaymentTransaction::query()
                        ->where('order_id', $locked->id)
                        ->where('gateway', PaymentGateway::VNPAY)
                        ->where('status', PaymentTransactionStatus::PENDING)
                        ->update([
                            'status' => PaymentTransactionStatus::EXPIRED,
                            'completed_at' => now(),
                        ]);

                    $this->orderInventory->releaseReservedForOrder($locked);

                    $locked->update([
                        'payment_status' => PaymentStatus::FAILED,
                        'current_status' => OrderStatus::CANCELLED,
                    ]);

                    OrderTimeline::query()->create([
                        'order_id' => $locked->id,
                        'status' => OrderStatus::CANCELLED->value,
                        'note' => 'VNPay payment window expired.',
                    ]);
                });
                $count++;
            } catch (\Throwable $e) {
                Log::error('Expire VNPay pending payment failed', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Processed {$count} expired VNPay orders.");

        return self::SUCCESS;
    }
}
