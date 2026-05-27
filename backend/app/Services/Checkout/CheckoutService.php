<?php

namespace App\Services\Checkout;

use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentMethod;
use App\Enums\Order\PaymentStatus;
use App\Models\Account;
use App\Models\Address;
use App\Models\Book;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderTimeline;
use App\Models\ShippingMethod;
use App\Notifications\Order\NewOrderNeedsProcessingNotification;
use App\Services\Admin\AdminNotificationService;
use App\Services\Order\OrderStatusTransitionService;
use App\Services\Payment\VnPayService;
use App\Exceptions\Promotion\PromotionUnavailableException;
use App\Services\Promotion\PromotionAllocationService;
use App\Services\Promotion\PromotionCheckoutPricingValidator;
use App\Services\Promotion\PromotionPricingService;
use App\Services\Promotion\FlashSaleResolver;
use App\Services\Shipping\ShippingAddressSnapshotFormatter;
use App\Services\Shipping\ShippingQuoteService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

class CheckoutService
{
    public function __construct(
        private VnPayService $vnPayService,
        private ShippingQuoteService $shippingQuoteService,
        private ShippingAddressSnapshotFormatter $addressSnapshotFormatter,
        private AdminNotificationService $adminNotificationService,
        private FlashSaleResolver $flashSaleResolver,
        private PromotionPricingService $promotionPricing,
        private PromotionAllocationService $promotionAllocation,
        private PromotionCheckoutPricingValidator $promotionCheckoutPricing,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{order: Order, payment: array<string, mixed>|null}
     */
    public function checkout(Account $account, array $input, string $clientIp): array
    {
        try {
            return DB::transaction(function () use ($account, $input, $clientIp): array {
                $existing = Order::query()
                    ->where('account_id', $account->id)
                    ->where('checkout_idempotency_key', $input['idempotency_key'])
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    return $this->finalizePaymentSideEffects($existing, $clientIp);
                }

                $shippingMethod = ShippingMethod::query()
                    ->whereKey((int) $input['shipping_method_id'])
                    ->where('is_active', true)
                    ->first();

                if ($shippingMethod === null) {
                    throw ValidationException::withMessages([
                        'shipping_method_id' => ['The selected shipping method is invalid or inactive.'],
                    ]);
                }

                $cart = Cart::query()
                    ->where('account_id', $account->id)
                    ->lockForUpdate()
                    ->first();

                if ($cart === null) {
                    throw ValidationException::withMessages([
                        'cart' => ['Your cart is empty.'],
                    ]);
                }

                $cartItems = CartItem::query()
                    ->where('cart_id', $cart->id)
                    ->where('selected', true)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                if ($cartItems->isEmpty()) {
                    throw ValidationException::withMessages([
                        'cart' => ['No line items selected for checkout.'],
                    ]);
                }

                $bookIds = $cartItems->pluck('book_id')->unique()->values()->all();

                /** @var \Illuminate\Support\Collection<int, Book> $books */
                $books = Book::query()
                    ->whereIn('id', $bookIds)
                    ->active()
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                if ($books->count() !== count($bookIds)) {
                    throw ValidationException::withMessages([
                        'cart' => ['One or more selected books are unavailable.'],
                    ]);
                }

                /** @var \Illuminate\Support\Collection<int, Inventory> $inventories */
                $inventories = Inventory::query()
                    ->whereIn('book_id', $bookIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('book_id');

                if ($inventories->count() !== count($bookIds)) {
                    throw ValidationException::withMessages([
                        'cart' => ['Inventory is missing for one or more books.'],
                    ]);
                }

                foreach ($cartItems as $line) {
                    $bookId = (int) $line->book_id;
                    $qty = (int) $line->quantity;
                    $inv = $inventories->get($bookId);
                    if (! $inv instanceof Inventory) {
                        throw ValidationException::withMessages([
                            'cart' => ['Inventory is missing for one or more books.'],
                        ]);
                    }
                    $available = max(0, (int) $inv->quantity - (int) $inv->reserved_quantity);
                    if ($qty > $available) {
                        throw ValidationException::withMessages([
                            'cart' => ["Insufficient stock for book ID {$bookId}."],
                        ]);
                    }
                }

                [$shippingName, $shippingPhone, $shippingAddressText, $provinceCode] = $this->resolveShippingFields($account, $input);

                $shippingFee = $this->shippingQuoteService->resolveBaseFee((int) $shippingMethod->id, $provinceCode);

                $quantityByBookId = $cartItems
                    ->mapWithKeys(static fn (CartItem $line): array => [(int) $line->book_id => (int) $line->quantity])
                    ->all();

                $promotionItems = $this->flashSaleResolver->activeItemsForBooks(
                    $bookIds,
                    (int) $account->id,
                    $quantityByBookId,
                );

                $expectations = $input['pricing_expectations'] ?? [];

                $this->promotionCheckoutPricing->validate(
                    $account,
                    $cartItems,
                    $books,
                    $promotionItems,
                    $expectations,
                );

                $totalAmount = '0.00';
                $lineRows = [];
                foreach ($cartItems as $line) {
                    $bookId = (int) $line->book_id;
                    $book = $books->get($bookId);
                    if (! $book instanceof Book) {
                        throw ValidationException::withMessages([
                            'cart' => ['One or more selected books are unavailable.'],
                        ]);
                    }
                    $qty = (int) $line->quantity;
                    $unit = (string) $book->selling_price;
                    $promotionItem = $promotionItems->get($bookId);
                    $pricing = $this->promotionPricing->calculateLine(
                        $unit,
                        $qty,
                        $promotionItem !== null ? (string) $promotionItem->discount_value : null,
                    );

                    $totalAmount = bcadd($totalAmount, $pricing['line_total'], 2);
                    $lineRows[] = [
                        'book_id' => $bookId,
                        'promotion_id' => $promotionItem?->promotion_id,
                        'promotion_item_id' => $promotionItem?->id,
                        'promotion_item' => $promotionItem,
                        'price' => $pricing['unit_price'],
                        'quantity' => $qty,
                        'total_price' => $pricing['line_total'],
                        'discount_amount' => $pricing['discount_amount'],
                    ];
                }

                $finalAmount = bcadd($totalAmount, (string) $shippingFee, 2);

                $paymentMethod = PaymentMethod::from($input['payment_method']);
                $isCod = $paymentMethod === PaymentMethod::COD;
                $initialOrderStatus = $isCod ? OrderStatus::CONFIRMED : OrderStatus::PENDING;

                $order = Order::query()->create([
                    'account_id' => $account->id,
                    'checkout_idempotency_key' => $input['idempotency_key'],
                    'shipping_method_id' => $shippingMethod->id,
                    'total_amount' => $totalAmount,
                    'shipping_fee' => (string) $shippingFee,
                    'final_amount' => $finalAmount,
                    'shipping_name' => $shippingName,
                    'shipping_phone' => $shippingPhone,
                    'shipping_address' => $shippingAddressText,
                    'payment_method' => $paymentMethod,
                    'payment_status' => PaymentStatus::PENDING,
                    'payment_expires_at' => null,
                    'note' => $input['note'] ?? null,
                    'current_status' => $initialOrderStatus,
                ]);

                usort($lineRows, static function (array $left, array $right): int {
                    $leftId = $left['promotion_item_id'] ?? 0;
                    $rightId = $right['promotion_item_id'] ?? 0;

                    return (int) $leftId <=> (int) $rightId;
                });

                foreach ($lineRows as $row) {
                    $orderItem = OrderItem::query()->create([
                        'order_id' => $order->id,
                        'book_id' => $row['book_id'],
                        'promotion_id' => $row['promotion_id'],
                        'promotion_item_id' => $row['promotion_item_id'],
                        'price' => $row['price'],
                        'quantity' => $row['quantity'],
                        'total_price' => $row['total_price'],
                        'discount_amount' => $row['discount_amount'],
                        'is_reviewed' => false,
                    ]);

                    if ($row['promotion_item'] !== null) {
                        try {
                            $this->promotionAllocation->reserve($account, $row['promotion_item'], $orderItem);
                        } catch (ValidationException $allocationException) {
                            if ($allocationException->errors()['promotion'] ?? null) {
                                throw new PromotionUnavailableException;
                            }

                            throw $allocationException;
                        }
                    }
                }

                foreach ($cartItems as $line) {
                    $bookId = (int) $line->book_id;
                    $qty = (int) $line->quantity;
                    $inv = $inventories->get($bookId);
                    if (! $inv instanceof Inventory) {
                        throw ValidationException::withMessages([
                            'cart' => ['Inventory is missing for one or more books.'],
                        ]);
                    }
                    $inv->increment('reserved_quantity', $qty);
                }

                OrderTimeline::query()->create([
                    'order_id' => $order->id,
                    'status' => $initialOrderStatus->value,
                    'note' => $isCod
                        ? OrderStatusTransitionService::TIMELINE_NOTE_CHECKOUT_COD
                        : OrderStatusTransitionService::TIMELINE_NOTE_CHECKOUT_VNPAY_PENDING,
                ]);

                foreach ($cartItems as $line) {
                    $line->delete();
                }

                $order->refresh();
                $order->load(['items', 'shippingMethod']);

                if ($isCod) {
                    DB::afterCommit(fn () => $this->adminNotificationService->notifyActiveAdmins(
                        new NewOrderNeedsProcessingNotification($order->fresh(['account']) ?? $order)
                    ));
                }

                return $this->finalizePaymentSideEffects($order, $clientIp);
            });
        } catch (ValidationException|PromotionUnavailableException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Checkout failed', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @return array{order: Order, payment: array<string, mixed>|null}
     */
    private function finalizePaymentSideEffects(Order $order, string $clientIp): array
    {
        $order->loadMissing(['items', 'shippingMethod']);

        if ($order->payment_method !== PaymentMethod::VNPAY) {
            return [
                'order' => $order,
                'payment' => null,
            ];
        }

        if ($order->payment_status === PaymentStatus::PAID) {
            return [
                'order' => $order,
                'payment' => null,
            ];
        }

        try {
            $pay = $this->vnPayService->createPaymentUrl($order, $clientIp);
        } catch (InvalidArgumentException $e) {
            Log::warning('VNPay URL not created on checkout', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);

            return [
                'order' => $order->fresh(['items', 'shippingMethod']),
                'payment' => null,
            ];
        }

        return [
            'order' => $order->fresh(['items', 'shippingMethod']),
            'payment' => $pay,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{0: string, 1: string, 2: string, 3: string}
     */
    private function resolveShippingFields(Account $account, array $input): array
    {
        if (! empty($input['address_id'])) {
            $address = Address::query()
                ->where('account_id', $account->id)
                ->whereKey((int) $input['address_id'])
                ->first();

            if ($address === null) {
                throw ValidationException::withMessages([
                    'address_id' => ['Address not found.'],
                ]);
            }

            if ($address->province_code === null || $address->province_code === '') {
                throw ValidationException::withMessages([
                    'address_id' => ['Selected address is missing province_code; cannot compute shipping fee.'],
                ]);
            }

            return [
                $address->recipient_name,
                $address->recipient_phone,
                $this->addressSnapshotFormatter->format(
                    $address->detail_address,
                    (string) $address->ward_code,
                    (string) $address->province_code,
                ),
                (string) $address->province_code,
            ];
        }

        $s = $input['shipping'];

        return [
            (string) $s['recipient_name'],
            (string) $s['recipient_phone'],
            $this->addressSnapshotFormatter->format(
                (string) $s['detail_address'],
                (string) $s['ward_code'],
                (string) $s['province_code'],
            ),
            (string) $s['province_code'],
        ];
    }
}
