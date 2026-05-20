<?php

namespace App\Services\Payment;

use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentMethod;
use App\Enums\Order\PaymentStatus;
use App\Enums\Payment\PaymentGateway;
use App\Enums\Payment\PaymentTransactionStatus;
use App\Enums\Payment\PaymentTransactionType;
use App\Models\Order;
use App\Models\OrderTimeline;
use App\Models\PaymentTransaction;
use App\Services\Order\OrderStatusTransitionService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class VnPayService
{
    public function createPaymentUrl(Order $order, string $clientIp): array
    {
        $this->assertConfigured();

        if ($order->payment_method !== PaymentMethod::VNPAY) {
            throw new InvalidArgumentException('Order payment method must be VNPay.');
        }

        if ($order->current_status === OrderStatus::CANCELLED) {
            throw new InvalidArgumentException('Cannot create VNPay payment for a cancelled order.');
        }

        if ($order->payment_status === PaymentStatus::PAID) {
            throw new InvalidArgumentException('Order is already paid.');
        }

        if ((float) $order->final_amount <= 0) {
            throw new InvalidArgumentException('Order final amount must be greater than zero.');
        }

        try {
            return DB::transaction(function () use ($order, $clientIp): array {
                /** @var Order $order */
                $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

                if ($order->payment_method !== PaymentMethod::VNPAY) {
                    throw new InvalidArgumentException('Order payment method must be VNPay.');
                }

                if ($order->current_status === OrderStatus::CANCELLED || $order->payment_status === PaymentStatus::PAID) {
                    throw new InvalidArgumentException('Order is not eligible for VNPay payment.');
                }

                $ttlHours = max(1, (int) config('vnpay.payment_ttl_hours', 12));
                $expiresAt = Carbon::now()->addHours($ttlHours);

                $pending = PaymentTransaction::query()
                    ->where('order_id', $order->id)
                    ->where('gateway', PaymentGateway::VNPAY)
                    ->where('status', PaymentTransactionStatus::PENDING)
                    ->orderByDesc('id')
                    ->first();

                if (
                    $pending !== null
                    && $order->payment_expires_at instanceof CarbonInterface
                    && $order->payment_expires_at->isFuture()
                ) {
                    $existingUrl = is_array($pending->payload) ? ($pending->payload['payment_url'] ?? null) : null;
                    if (is_string($existingUrl) && $existingUrl !== '') {
                        return [
                            'payment_url' => $existingUrl,
                            'payment_transaction_id' => $pending->id,
                            'vnp_TxnRef' => $pending->gateway_txn_id,
                            'expires_at' => $order->payment_expires_at->toIso8601String(),
                        ];
                    }
                }

                if ($pending !== null) {
                    $pending->update([
                        'status' => PaymentTransactionStatus::EXPIRED,
                        'payload' => array_merge(is_array($pending->payload) ? $pending->payload : [], [
                            'expired_reason' => 'superseded_or_ttl',
                        ]),
                    ]);
                }

                $vnpTxnRef = $this->makeTxnRef($order->id);

                $params = $this->buildCreateParams($order, $vnpTxnRef, $clientIp, $expiresAt);
                $secureHash = $this->secureHash($params);
                $paymentUrl = $this->buildPaymentRedirectUrl($params, $secureHash);

                $transaction = PaymentTransaction::query()->create([
                    'order_id' => $order->id,
                    'gateway' => PaymentGateway::VNPAY,
                    'gateway_txn_id' => $vnpTxnRef,
                    'type' => PaymentTransactionType::PAYMENT,
                    'amount' => $order->final_amount,
                    'status' => PaymentTransactionStatus::PENDING,
                    'payload' => [
                        'payment_url' => $paymentUrl,
                        'vnp_TxnRef' => $vnpTxnRef,
                        'vnp_CreateDate' => $params['vnp_CreateDate'] ?? null,
                        'vnp_ExpireDate' => $params['vnp_ExpireDate'] ?? null,
                    ],
                ]);

                $order->update([
                    'payment_expires_at' => $expiresAt,
                    'payment_status' => PaymentStatus::PENDING,
                ]);

                return [
                    'payment_url' => $paymentUrl,
                    'payment_transaction_id' => $transaction->id,
                    'vnp_TxnRef' => $vnpTxnRef,
                    'expires_at' => $expiresAt->toIso8601String(),
                ];
            });
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('VNPay create payment failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            throw new RuntimeException('Unable to create VNPay payment.', previous: $e);
        }
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function handleReturn(array $query): array
    {
        $this->assertConfigured();

        $params = $this->filterVnpParams($query);
        $secureHash = $params['vnp_SecureHash'] ?? null;
        if (! is_string($secureHash) || $secureHash === '') {
            return ['success' => false, 'message' => 'Missing vnp_SecureHash.'];
        }

        $expected = $this->secureHash($params);
        if (! hash_equals(strtolower($expected), strtolower($secureHash))) {
            Log::warning('VNPay return invalid signature', [
                'vnp_TxnRef' => $query['vnp_TxnRef'] ?? null,
            ]);

            return ['success' => false, 'message' => 'Invalid signature.'];
        }

        $txnRef = $query['vnp_TxnRef'] ?? null;
        if (! is_string($txnRef) || $txnRef === '') {
            return ['success' => false, 'message' => 'Missing vnp_TxnRef.'];
        }

        $responseCode = $query['vnp_ResponseCode'] ?? null;
        $transactionStatus = $query['vnp_TransactionStatus'] ?? null;
        $amountMinor = $query['vnp_Amount'] ?? null;

        try {
            return DB::transaction(function () use ($query, $txnRef, $responseCode, $transactionStatus, $amountMinor): array {
                /** @var PaymentTransaction|null $paymentTransaction */
                $paymentTransaction = PaymentTransaction::query()
                    ->where('gateway', PaymentGateway::VNPAY)
                    ->where('gateway_txn_id', $txnRef)
                    ->lockForUpdate()
                    ->first();

                if ($paymentTransaction === null) {
                    return ['success' => false, 'message' => 'Unknown transaction reference.'];
                }

                $order = Order::query()->whereKey($paymentTransaction->order_id)->lockForUpdate()->first();
                if ($order === null) {
                    return ['success' => false, 'message' => 'Order not found.'];
                }

                if ($order->payment_method !== PaymentMethod::VNPAY) {
                    return ['success' => false, 'message' => 'Order payment method mismatch.'];
                }

                if ($paymentTransaction->status === PaymentTransactionStatus::PAID) {
                    return [
                        'success' => true,
                        'idempotent' => true,
                        'order_id' => $order->id,
                        'payment_status' => $order->payment_status?->value,
                    ];
                }

                $expectedMinor = (string) (int) round((float) $order->final_amount * 100);
                if (! is_string($amountMinor) && ! is_numeric($amountMinor)) {
                    return ['success' => false, 'message' => 'Missing vnp_Amount.'];
                }
                if ((string) $amountMinor !== $expectedMinor) {
                    Log::warning('VNPay return amount mismatch', [
                        'order_id' => $order->id,
                        'expected_minor' => $expectedMinor,
                        'received_minor' => (string) $amountMinor,
                    ]);

                    return ['success' => false, 'message' => 'Amount mismatch.'];
                }

                if ($order->current_status === OrderStatus::CANCELLED) {
                    return ['success' => false, 'message' => 'Order is cancelled.'];
                }

                if ($order->payment_expires_at instanceof CarbonInterface && $order->payment_expires_at->isPast()) {
                    return ['success' => false, 'message' => 'Payment window expired.'];
                }

                $payload = array_merge(is_array($paymentTransaction->payload) ? $paymentTransaction->payload : [], [
                    'return' => $this->sanitizeReturnPayload($query),
                ]);
                $paymentTransaction->update(['payload' => $payload]);

                $isSuccess = $responseCode === '00' && $transactionStatus === '00';

                if ($isSuccess) {
                    $paymentTransaction->update([
                        'status' => PaymentTransactionStatus::PAID,
                        'completed_at' => now(),
                    ]);

                    $order->update([
                        'payment_status' => PaymentStatus::PAID,
                    ]);

                    if ($order->current_status === OrderStatus::PENDING) {
                        $order->update(['current_status' => OrderStatus::CONFIRMED]);
                        OrderTimeline::query()->create([
                            'order_id' => $order->id,
                            'status' => OrderStatus::CONFIRMED->value,
                            'note' => OrderStatusTransitionService::TIMELINE_NOTE_VNPAY_PAID,
                        ]);
                    } else {
                        OrderTimeline::query()->create([
                            'order_id' => $order->id,
                            'status' => $order->current_status->value,
                            'note' => OrderStatusTransitionService::TIMELINE_NOTE_VNPAY_PAID,
                        ]);
                    }

                    return [
                        'success' => true,
                        'order_id' => $order->id,
                        'payment_status' => PaymentStatus::PAID->value,
                        'vnp_ResponseCode' => $responseCode,
                        'vnp_TransactionStatus' => $transactionStatus,
                    ];
                }

                $paymentTransaction->update([
                    'status' => PaymentTransactionStatus::FAILED,
                    'completed_at' => now(),
                ]);

                $order->update([
                    'payment_status' => PaymentStatus::FAILED,
                ]);

                OrderTimeline::query()->create([
                    'order_id' => $order->id,
                    'status' => $order->current_status->value,
                    'note' => OrderStatusTransitionService::TIMELINE_NOTE_VNPAY_FAILED,
                ]);

                return [
                    'success' => false,
                    'order_id' => $order->id,
                    'payment_status' => PaymentStatus::FAILED->value,
                    'vnp_ResponseCode' => $responseCode,
                    'vnp_TransactionStatus' => $transactionStatus,
                    'message' => 'Payment not successful.',
                ];
            });
        } catch (\Throwable $e) {
            Log::error('VNPay return handling failed', [
                'vnp_TxnRef' => $txnRef,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Unable to process VNPay return.'];
        }
    }

    private function assertConfigured(): void
    {
        $tmn = config('vnpay.tmn_code');
        $secret = config('vnpay.hash_secret');
        $url = config('vnpay.payment_url');
        $returnUrl = config('vnpay.return_url');

        if (! is_string($tmn) || $tmn === '' || ! is_string($secret) || $secret === '' || ! is_string($url) || $url === '' || ! is_string($returnUrl) || $returnUrl === '') {
            throw new RuntimeException('VNPay is not configured (VNP_TMN_CODE, VNP_HASH_SECRET, VNP_URL, VNP_RETURN_URL).');
        }
    }

    private function makeTxnRef(int $orderId): string
    {
        return 'B'.$orderId.'T'.Str::upper(Str::random(10));
    }

    /**
     * VNPay builds hashData with sorted keys and {@see urlencode()} on each key and value (not RFC3986 query).
     *
     * @param  array<string, mixed>  $params
     */
    private function secureHash(array $params): string
    {
        $data = $params;
        unset($data['vnp_SecureHash'], $data['vnp_SecureHashType']);

        ksort($data);

        return hash_hmac('sha512', $this->buildHashDataString($data), (string) config('vnpay.hash_secret'));
    }

    /**
     * @param  array<string, mixed>  $params  without vnp_SecureHash / vnp_SecureHashType
     */
    private function buildHashDataString(array $params): string
    {
        $hashData = '';
        $i = 0;
        foreach ($params as $key => $value) {
            $encodedKey = urlencode((string) $key);
            $encodedValue = urlencode((string) $value);
            if ($i === 1) {
                $hashData .= '&'.$encodedKey.'='.$encodedValue;
            } else {
                $hashData .= $encodedKey.'='.$encodedValue;
                $i = 1;
            }
        }

        return $hashData;
    }

    /**
     * @param  array<string, mixed>  $paramsWithoutHash  sorted by caller; must not contain vnp_SecureHash
     */
    private function buildPaymentRedirectUrl(array $paramsWithoutHash, string $secureHash): string
    {
        $data = $paramsWithoutHash;
        ksort($data);

        $query = '';
        foreach ($data as $key => $value) {
            $query .= urlencode((string) $key).'='.urlencode((string) $value).'&';
        }

        $base = rtrim((string) config('vnpay.payment_url'), '?&');

        return $base.'?'.$query.'vnp_SecureHash='.$secureHash;
    }

    /**
     * @return array<string, string>
     */
    private function buildCreateParams(Order $order, string $vnpTxnRef, string $clientIp, CarbonInterface $expiresAt): array
    {
        $timezone = config('app.timezone') ?: 'UTC';
        $now = Carbon::now($timezone);
        $amountMinor = (string) (int) round((float) $order->final_amount * 100);

        $params = [
            'vnp_Version' => (string) config('vnpay.version'),
            'vnp_Command' => (string) config('vnpay.command'),
            'vnp_TmnCode' => (string) config('vnpay.tmn_code'),
            'vnp_Locale' => (string) config('vnpay.locale'),
            'vnp_CurrCode' => (string) config('vnpay.curr_code'),
            'vnp_TxnRef' => $vnpTxnRef,
            'vnp_OrderInfo' => 'Thanh toan don hang #'.$order->id,
            'vnp_OrderType' => 'other',
            'vnp_Amount' => $amountMinor,
            'vnp_ReturnUrl' => (string) config('vnpay.return_url'),
            'vnp_IpAddr' => Str::limit($clientIp, 45, ''),
            'vnp_CreateDate' => $now->format('YmdHis'),
            'vnp_ExpireDate' => Carbon::parse($expiresAt, $timezone)->format('YmdHis'),
        ];

        return $params;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, string>
     */
    private function filterVnpParams(array $query): array
    {
        $out = [];
        foreach ($query as $key => $value) {
            if (! is_string($key) || ! Str::startsWith($key, 'vnp_')) {
                continue;
            }
            if ($value === null) {
                continue;
            }
            $out[$key] = is_scalar($value) ? (string) $value : '';
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function sanitizeReturnPayload(array $query): array
    {
        $allowed = [
            'vnp_TxnRef',
            'vnp_Amount',
            'vnp_OrderInfo',
            'vnp_ResponseCode',
            'vnp_TransactionNo',
            'vnp_TransactionStatus',
            'vnp_PayDate',
            'vnp_BankCode',
            'vnp_BankTranNo',
            'vnp_CardType',
        ];

        $out = [];
        foreach ($allowed as $key) {
            if (! array_key_exists($key, $query)) {
                continue;
            }
            $value = $query[$key];
            if (is_scalar($value) || $value === null) {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
