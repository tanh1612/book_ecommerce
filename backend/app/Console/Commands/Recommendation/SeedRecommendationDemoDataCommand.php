<?php

namespace App\Console\Commands\Recommendation;

use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentMethod;
use App\Enums\Order\PaymentStatus;
use App\Enums\Payment\PaymentGateway;
use App\Enums\Payment\PaymentTransactionStatus;
use App\Enums\Payment\PaymentTransactionType;
use App\Enums\Recommendation\BookInteractionType;
use App\Enums\Review\ReviewStatus;
use App\Jobs\Recommendation\BuildPopularRecommendations;
use App\Jobs\Recommendation\BuildUserRecommendations;
use App\Models\Account;
use App\Models\Book;
use App\Models\ShippingMethod;
use App\Models\UserProfile;
use App\Services\Order\OrderStatusTransitionService;
use App\Services\Recommendation\RecommendationCacheService;
use App\Services\Recommendation\RecommendationCandidateService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SeedRecommendationDemoDataCommand extends Command
{
    protected $signature = 'recommendations:seed-demo-data {--password=Demo@123456 : Password for demo accounts}';

    protected $description = 'Seed controlled recommendation demo signals from the existing book catalog';

    /**
     * @var array<string, array{email: string, first_name: string, last_name: string}>
     */
    private array $demoUsers = [
        'focused_a' => [
            'email' => 'demo.recommendation.a@example.com',
            'first_name' => 'Demo',
            'last_name' => 'Reader A',
        ],
        'focused_b' => [
            'email' => 'demo.recommendation.b@example.com',
            'first_name' => 'Demo',
            'last_name' => 'Reader B',
        ],
        'cold' => [
            'email' => 'demo.recommendation.cold@example.com',
            'first_name' => 'Demo',
            'last_name' => 'Cold Start',
        ],
    ];

    /**
     * @var list<OrderStatus>
     */
    private array $terminalOrderStatuses = [
        OrderStatus::COMPLETED,
        OrderStatus::CANCELLED,
        OrderStatus::REFUND_EXPIRED,
    ];

    public function handle(
        RecommendationCandidateService $candidateService,
        RecommendationCacheService $cacheService,
    ): int {
        try {
            $password = (string) $this->option('password');
            if (trim($password) === '') {
                $this->error('Password must not be empty.');

                return self::INVALID;
            }

            $categories = $this->categoryIdsForPersonas();
            if ($categories->count() < 2) {
                $this->error('Need at least two categories with active books to seed recommendation demo data.');

                return self::FAILURE;
            }

            $shippingMethodId = $this->ensureShippingMethod();
            $accounts = $this->upsertDemoAccounts($password);
            $demoDates = $this->demoDates();

            DB::transaction(function () use ($accounts, $categories, $shippingMethodId, $demoDates): void {
                $this->resetDemoSignals($accounts);
                $this->upsertDemoAddresses($accounts);

                $personaA = $this->booksForCategory((int) $categories[0], 10);
                $personaB = $this->booksForCategory((int) $categories[1], 10);
                $popularBooks = $this->popularDemoBooks($categories, 24);

                $selectedBookIds = $personaA
                    ->merge($personaB)
                    ->merge($popularBooks)
                    ->pluck('id')
                    ->unique()
                    ->values();

                $this->assertInventoryRowsExist($selectedBookIds);
                $this->seedPersonaSignals($accounts['focused_a'], $personaA, $shippingMethodId, 'demo_persona_a', $demoDates[2]);
                $this->seedPersonaSignals($accounts['focused_b'], $personaB, $shippingMethodId, 'demo_persona_b', $demoDates[5]);
                $this->seedPopularOrders($accounts, $popularBooks, $shippingMethodId, $demoDates);
                $this->seedTerminalStateOrders($accounts, $popularBooks, $shippingMethodId, $demoDates);
                $this->refreshBookReviewAggregates($selectedBookIds);
            });

            $this->buildRecommendationCaches($accounts, $candidateService, $cacheService);
            $this->printSummary($accounts, $password);

            return self::SUCCESS;
        } catch (Throwable $e) {
            Log::error('Seed recommendation demo data command failed', [
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            $this->error('Failed to seed recommendation demo data: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @return Collection<int, int>
     */
    private function categoryIdsForPersonas(): Collection
    {
        return DB::table('book_categories')
            ->join('books', 'books.id', '=', 'book_categories.book_id')
            ->where('books.is_active', true)
            ->selectRaw('book_categories.category_id, COUNT(DISTINCT books.id) as books_count')
            ->groupBy('book_categories.category_id')
            ->havingRaw('COUNT(DISTINCT books.id) >= 10')
            ->orderByDesc('books_count')
            ->limit(4)
            ->pluck('book_categories.category_id')
            ->map(static fn ($id): int => (int) $id);
    }

    /**
     * @param  array<string, Account>  $accounts
     */
    private function resetDemoSignals(array $accounts): void
    {
        $accountIds = collect($accounts)
            ->map(static fn (Account $account): int => (int) $account->id)
            ->values()
            ->all();

        $this->restoreCompletedDemoInventorySales($accountIds);

        DB::table('book_interaction_events')->whereIn('account_id', $accountIds)->delete();
        DB::table('wishlists')->whereIn('account_id', $accountIds)->delete();
        DB::table('reviews')->whereIn('account_id', $accountIds)->delete();
        DB::table('addresses')->whereIn('account_id', $accountIds)->delete();
        DB::table('orders')->whereIn('account_id', $accountIds)->delete();
    }

    /**
     * @return array<string, Account>
     */
    private function upsertDemoAccounts(string $password): array
    {
        $accounts = [];

        foreach ($this->demoUsers as $key => $user) {
            /** @var Account $account */
            $account = Account::query()->updateOrCreate(
                ['email' => $user['email']],
                [
                    'password' => Hash::make($password),
                    'role' => 'customer',
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );

            UserProfile::query()->updateOrCreate(
                ['account_id' => $account->id],
                [
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'phone' => '0900000000',
                    'updated_at' => now(),
                ],
            );

            $accounts[$key] = $account->fresh();
        }

        return $accounts;
    }

    /**
     * @param  array<string, Account>  $accounts
     */
    private function upsertDemoAddresses(array $accounts): void
    {
        foreach (array_values($accounts) as $index => $account) {
            DB::table('addresses')->insert([
                'account_id' => $account->id,
                'recipient_name' => $account->profile?->full_name ?: 'Recommendation Demo',
                'recipient_phone' => '090000000'.($index + 1),
                'province_code' => '79',
                'district_code' => '760',
                'ward_code' => '26734',
                'detail_address' => 'Demo recommendation address '.($index + 1),
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function ensureShippingMethod(): int
    {
        /** @var ShippingMethod $method */
        $method = ShippingMethod::query()->firstOrCreate(
            ['name' => 'Demo recommendation shipping'],
            [
                'description' => 'Shipping method used for recommendation demo orders.',
                'is_active' => true,
            ],
        );

        return (int) $method->id;
    }

    /**
     * @return Collection<int, Book>
     */
    private function booksForCategory(int $categoryId, int $limit): Collection
    {
        return Book::query()
            ->active()
            ->whereHas('categories', fn ($query) => $query->where('categories.id', $categoryId))
            ->with(['categories:id,name', 'authors:id,name'])
            ->orderByDesc('review_count')
            ->orderByDesc('average_rating')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'name', 'slug', 'selling_price', 'average_rating', 'review_count', 'created_at']);
    }

    /**
     * @param  Collection<int, int>  $categoryIds
     * @return Collection<int, Book>
     */
    private function popularDemoBooks(Collection $categoryIds, int $limit): Collection
    {
        return Book::query()
            ->active()
            ->whereHas('categories', fn ($query) => $query->whereIn('categories.id', $categoryIds->all()))
            ->with(['categories:id,name', 'authors:id,name'])
            ->orderByDesc('review_count')
            ->orderByDesc('average_rating')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'name', 'slug', 'selling_price', 'average_rating', 'review_count', 'created_at']);
    }

    /**
     * @param  Collection<int, int>  $bookIds
     */
    private function assertInventoryRowsExist(Collection $bookIds): void
    {
        $expectedIds = $bookIds
            ->map(static fn ($bookId): int => (int) $bookId)
            ->unique()
            ->values();

        $existingIds = DB::table('inventories')
            ->whereIn('book_id', $expectedIds->all())
            ->pluck('book_id')
            ->map(static fn ($bookId): int => (int) $bookId);

        $missingIds = $expectedIds->diff($existingIds)->values();

        if ($missingIds->isNotEmpty()) {
            throw new RuntimeException('Missing inventory rows for demo books: '.$missingIds->implode(', '));
        }
    }

    /**
     * @param  list<int>  $accountIds
     */
    private function restoreCompletedDemoInventorySales(array $accountIds): void
    {
        $completedSales = DB::table('orders')
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->whereIn('orders.account_id', $accountIds)
            ->where('orders.note', 'recommendation_demo')
            ->where('orders.current_status', OrderStatus::COMPLETED->value)
            ->selectRaw('order_items.book_id, SUM(order_items.quantity) as sold_quantity')
            ->groupBy('order_items.book_id')
            ->get();

        foreach ($completedSales as $sale) {
            $quantity = (int) $sale->sold_quantity;
            if ($quantity <= 0) {
                continue;
            }

            DB::table('inventories')
                ->where('book_id', (int) $sale->book_id)
                ->update([
                    'quantity' => DB::raw('quantity + '.$quantity),
                    'sold_quantity' => DB::raw('GREATEST(sold_quantity - '.$quantity.', 0)'),
                ]);
        }
    }

    /**
     * @param  Collection<int, Book>  $books
     */
    private function seedPersonaSignals(
        Account $account,
        Collection $books,
        int $shippingMethodId,
        string $source,
        Carbon $baseDate,
    ): void
    {
        $seedBooks = $books->take(5)->values();
        $reviewBook = $seedBooks->first();

        foreach ($seedBooks as $index => $book) {
            DB::table('book_interaction_events')->insert([
                'account_id' => $account->id,
                'book_id' => $book->id,
                'event_type' => BookInteractionType::View->value,
                'source' => $source,
                'created_at' => $baseDate->copy()->addHours($index),
            ]);
        }

        foreach ($seedBooks->take(2) as $book) {
            DB::table('book_interaction_events')->insert([
                'account_id' => $account->id,
                'book_id' => $book->id,
                'event_type' => BookInteractionType::CartAdd->value,
                'source' => $source,
                'created_at' => $baseDate->copy()->addDay(),
            ]);
        }

        foreach ($seedBooks->take(2) as $book) {
            DB::table('wishlists')->insert([
                'account_id' => $account->id,
                'book_id' => $book->id,
                'created_at' => $baseDate->copy()->addDays(2),
                'updated_at' => $baseDate->copy()->addDays(2),
            ]);
        }

        if ($reviewBook instanceof Book) {
            $orderItemId = $this->createDemoOrderWithItem(
                account: $account,
                book: $reviewBook,
                shippingMethodId: $shippingMethodId,
                quantity: 1,
                terminalStatus: OrderStatus::COMPLETED,
                paymentMethod: PaymentMethod::COD,
                createdAt: $baseDate->copy()->addDays(3),
            );

            DB::table('reviews')->insert([
                'account_id' => $account->id,
                'book_id' => $reviewBook->id,
                'order_item_id' => $orderItemId,
                'rating' => 5.0,
                'comment' => 'Demo recommendation preference signal.',
                'status' => ReviewStatus::APPROVED->value,
                'created_at' => $baseDate->copy()->addDays(4),
                'updated_at' => $baseDate->copy()->addDays(4),
            ]);

            DB::table('order_items')
                ->where('id', $orderItemId)
                ->update([
                    'is_reviewed' => true,
                    'updated_at' => $baseDate->copy()->addDays(4),
                ]);
        }
    }

    /**
     * @param  array<string, Account>  $accounts
     * @param  Collection<int, Book>  $books
     */
    private function seedPopularOrders(array $accounts, Collection $books, int $shippingMethodId, Collection $demoDates): void
    {
        $buyerPool = collect($accounts)->values();

        foreach ($books->take(18)->values() as $index => $book) {
            $ordersCount = max(1, 5 - intdiv($index, 4));
            $quantity = $index < 5 ? 3 : 1;

            for ($i = 0; $i < $ordersCount; $i++) {
                /** @var Account $buyer */
                $buyer = $buyerPool[($index + $i) % $buyerPool->count()];
                $date = $demoDates[($index + $i) % $demoDates->count()]->copy()->addHours($i);
                $paymentMethod = ($index + $i) % 2 === 0 ? PaymentMethod::COD : PaymentMethod::VNPAY;

                $this->createDemoOrderWithItem(
                    account: $buyer,
                    book: $book,
                    shippingMethodId: $shippingMethodId,
                    quantity: $quantity,
                    terminalStatus: OrderStatus::COMPLETED,
                    paymentMethod: $paymentMethod,
                    createdAt: $date,
                );
            }
        }
    }

    /**
     * @param  array<string, Account>  $accounts
     * @param  Collection<int, Book>  $books
     * @param  Collection<int, Carbon>  $demoDates
     */
    private function seedTerminalStateOrders(array $accounts, Collection $books, int $shippingMethodId, Collection $demoDates): void
    {
        $buyerPool = collect($accounts)->values();
        $books = $books->values();

        foreach ($this->terminalOrderStatuses as $statusIndex => $terminalStatus) {
            for ($i = 0; $i < 4; $i++) {
                /** @var Account $buyer */
                $buyer = $buyerPool[($statusIndex + $i) % $buyerPool->count()];
                /** @var Book $book */
                $book = $books[($statusIndex * 4 + $i) % $books->count()];
                $date = $this->demoDateForTerminalStatus(
                    $demoDates[($statusIndex * 4 + $i + 3) % $demoDates->count()]->copy()->addHours($statusIndex + $i),
                    $terminalStatus,
                );

                $paymentMethod = $terminalStatus === OrderStatus::CANCELLED && $i % 2 === 0
                    ? PaymentMethod::VNPAY
                    : PaymentMethod::COD;

                if ($terminalStatus === OrderStatus::REFUND_EXPIRED) {
                    $paymentMethod = PaymentMethod::VNPAY;
                }

                $this->createDemoOrderWithItem(
                    account: $buyer,
                    book: $book,
                    shippingMethodId: $shippingMethodId,
                    quantity: 1,
                    terminalStatus: $terminalStatus,
                    paymentMethod: $paymentMethod,
                    createdAt: $date,
                );
            }
        }
    }

    private function createDemoOrderWithItem(
        Account $account,
        Book $book,
        int $shippingMethodId,
        int $quantity,
        OrderStatus $terminalStatus,
        PaymentMethod $paymentMethod,
        Carbon $createdAt,
    ): int {
        $unitPrice = (float) $book->selling_price;
        $total = $unitPrice * $quantity;
        $shippingFee = $this->shippingFeeForOrder($createdAt, $quantity);
        $finalAmount = $total + $shippingFee;
        $completedAt = $this->terminalTimestamp($createdAt, $terminalStatus);
        $paymentStatus = $this->paymentStatusForTerminalOrder($terminalStatus);
        $paymentExpiresAt = $paymentMethod === PaymentMethod::VNPAY
            ? $createdAt->copy()->addHours(12)
            : null;
        $shippingSnapshot = $this->shippingSnapshotForAccount($account);

        $orderId = (int) DB::table('orders')->insertGetId([
            'account_id' => $account->id,
            'checkout_idempotency_key' => null,
            'shipping_method_id' => $shippingMethodId,
            'total_amount' => $total,
            'shipping_fee' => $shippingFee,
            'final_amount' => $finalAmount,
            'shipping_name' => $shippingSnapshot['name'],
            'shipping_phone' => $shippingSnapshot['phone'],
            'shipping_address' => $shippingSnapshot['address'],
            'payment_method' => $paymentMethod->value,
            'payment_status' => $paymentStatus->value,
            'payment_expires_at' => $paymentExpiresAt,
            'refund_deadline_at' => null,
            'note' => 'recommendation_demo',
            'current_status' => $terminalStatus->value,
            'created_at' => $createdAt,
            'updated_at' => $completedAt,
        ]);

        $orderItemId = (int) DB::table('order_items')->insertGetId([
            'order_id' => $orderId,
            'book_id' => $book->id,
            'promotion_item_id' => null,
            'promotion_id' => null,
            'price' => $unitPrice,
            'quantity' => $quantity,
            'total_price' => $total,
            'discount_amount' => null,
            'is_reviewed' => false,
            'created_at' => $createdAt,
            'updated_at' => $completedAt,
        ]);

        $this->createOrderTimelines($orderId, $terminalStatus, $paymentMethod, $createdAt, $finalAmount);
        $this->createPaymentTransactions($orderId, $paymentMethod, $terminalStatus, $finalAmount, $createdAt);
        $this->recordCompletedInventorySale((int) $book->id, $quantity, $terminalStatus);

        return $orderItemId;
    }

    private function recordCompletedInventorySale(int $bookId, int $quantity, OrderStatus $terminalStatus): void
    {
        if ($terminalStatus !== OrderStatus::COMPLETED || $quantity <= 0) {
            return;
        }

        DB::table('inventories')
            ->where('book_id', $bookId)
            ->update([
                'quantity' => DB::raw('GREATEST(quantity - '.((int) $quantity).', 0)'),
                'sold_quantity' => DB::raw('sold_quantity + '.((int) $quantity)),
            ]);
    }

    private function paymentStatusForTerminalOrder(OrderStatus $terminalStatus): PaymentStatus
    {
        return match ($terminalStatus) {
            OrderStatus::COMPLETED => PaymentStatus::PAID,
            OrderStatus::CANCELLED => PaymentStatus::CANCELLED,
            OrderStatus::REFUND_EXPIRED => PaymentStatus::REFUND_EXPIRED,
            default => PaymentStatus::PENDING,
        };
    }

    private function terminalTimestamp(Carbon $createdAt, OrderStatus $terminalStatus): Carbon
    {
        $timestamp = match ($terminalStatus) {
            OrderStatus::COMPLETED => $createdAt->copy()->addDays(4),
            OrderStatus::CANCELLED => $createdAt->copy()->addDays(2),
            OrderStatus::REFUND_EXPIRED => $createdAt->copy()->addDays(8),
            default => $createdAt->copy(),
        };

        return $timestamp->min(now()->copy()->subMinute());
    }

    private function timelineTimestamp(Carbon $createdAt, int $dayOffset, int $hourOffset): Carbon
    {
        return $createdAt
            ->copy()
            ->addDays($dayOffset)
            ->addHours($hourOffset)
            ->min(now()->copy()->subMinute());
    }

    private function createOrderTimelines(
        int $orderId,
        OrderStatus $terminalStatus,
        PaymentMethod $paymentMethod,
        Carbon $createdAt,
        float $finalAmount,
    ): void
    {
        $steps = $this->timelineStepsForDemoOrder($terminalStatus, $paymentMethod, $finalAmount);

        foreach ($steps as [$status, $note, $dayOffset, $hourOffset]) {
            DB::table('order_timelines')->insert([
                'order_id' => $orderId,
                'status' => $status->value,
                'note' => $note,
                'actor' => 'recommendation_demo',
                'created_at' => $this->timelineTimestamp($createdAt, $dayOffset, $hourOffset),
            ]);
        }
    }

    /**
     * @return list<array{0: OrderStatus, 1: string, 2: int, 3: int}>
     */
    private function timelineStepsForDemoOrder(
        OrderStatus $terminalStatus,
        PaymentMethod $paymentMethod,
        float $finalAmount,
    ): array
    {
        $checkoutNote = $paymentMethod === PaymentMethod::COD
            ? OrderStatusTransitionService::TIMELINE_NOTE_CHECKOUT_COD
            : OrderStatusTransitionService::TIMELINE_NOTE_CHECKOUT_VNPAY_PENDING;

        $paymentConfirmedSteps = $paymentMethod === PaymentMethod::VNPAY
            ? [[OrderStatus::CONFIRMED, OrderStatusTransitionService::TIMELINE_NOTE_VNPAY_PAID, 0, 2]]
            : [];

        if ($terminalStatus === OrderStatus::COMPLETED) {
            $codPaidSteps = $paymentMethod === PaymentMethod::COD
                ? [[OrderStatus::SHIPPING, $this->codPaidTimelineNote($finalAmount), 3, 2]]
                : [];

            return [
                [$paymentMethod === PaymentMethod::COD ? OrderStatus::CONFIRMED : OrderStatus::PENDING, $checkoutNote, 0, 0],
                ...$paymentConfirmedSteps,
                [OrderStatus::PROCESSING, OrderStatusTransitionService::TIMELINE_NOTE_PROCESS, 1, 0],
                [OrderStatus::SHIPPING, OrderStatusTransitionService::TIMELINE_NOTE_SHIP, 2, 0],
                ...$codPaidSteps,
                [OrderStatus::COMPLETED, OrderStatusTransitionService::TIMELINE_NOTE_COMPLETED, 4, 0],
            ];
        }

        if ($terminalStatus === OrderStatus::CANCELLED && $paymentMethod === PaymentMethod::VNPAY) {
            return [
                [OrderStatus::PENDING, $checkoutNote, 0, 0],
                [OrderStatus::CANCELLED, OrderStatusTransitionService::TIMELINE_NOTE_VNPAY_EXPIRED, 0, 13],
            ];
        }

        if ($terminalStatus === OrderStatus::CANCELLED) {
            return [
                [OrderStatus::CONFIRMED, $checkoutNote, 0, 0],
                [OrderStatus::CANCELLED, OrderStatusTransitionService::TIMELINE_NOTE_CANCEL_BY_CUSTOMER, 0, 6],
            ];
        }

        if ($terminalStatus === OrderStatus::REFUND_EXPIRED) {
            return [
                [OrderStatus::PENDING, $checkoutNote, 0, 0],
                ...$paymentConfirmedSteps,
                [OrderStatus::PROCESSING, OrderStatusTransitionService::TIMELINE_NOTE_PROCESS, 1, 0],
                [OrderStatus::SHIPPING, OrderStatusTransitionService::TIMELINE_NOTE_SHIP, 2, 0],
                [OrderStatus::CANCELLED, OrderStatusTransitionService::TIMELINE_NOTE_DELIVERY_FAILED_VNPAY_MANUAL_REFUND, 4, 0],
                [OrderStatus::REFUND_EXPIRED, OrderStatusTransitionService::TIMELINE_NOTE_REFUND_EXPIRED_NO_CONTACT, 8, 0],
            ];
        }

        return [];
    }

    private function createPaymentTransactions(
        int $orderId,
        PaymentMethod $paymentMethod,
        OrderStatus $terminalStatus,
        float $amount,
        Carbon $createdAt,
    ): void
    {
        if ($paymentMethod !== PaymentMethod::VNPAY) {
            return;
        }

        $paymentStatus = match ($terminalStatus) {
            OrderStatus::COMPLETED, OrderStatus::REFUND_EXPIRED => PaymentTransactionStatus::PAID,
            OrderStatus::CANCELLED => PaymentTransactionStatus::EXPIRED,
            default => PaymentTransactionStatus::PENDING,
        };

        DB::table('payment_transactions')->insert([
            'order_id' => $orderId,
            'gateway' => PaymentGateway::VNPAY->value,
            'gateway_txn_id' => 'DEMO-PAY-'.$orderId,
            'type' => PaymentTransactionType::PAYMENT->value,
            'amount' => $amount,
            'status' => $paymentStatus->value,
            'payload' => json_encode([
                'source' => 'recommendation_demo',
                'terminal_status' => $terminalStatus->value,
            ]),
            'completed_at' => $paymentStatus === PaymentTransactionStatus::PENDING ? null : $this->paymentTransactionCompletedAt($terminalStatus, $createdAt),
            'created_at' => $createdAt,
            'updated_at' => $this->paymentTransactionCompletedAt($terminalStatus, $createdAt),
        ]);

        if ($terminalStatus !== OrderStatus::REFUND_EXPIRED) {
            return;
        }

        DB::table('payment_transactions')->insert([
            'order_id' => $orderId,
            'gateway' => PaymentGateway::VNPAY->value,
            'gateway_txn_id' => 'DEMO-REFUND-'.$orderId,
            'type' => PaymentTransactionType::REFUND->value,
            'amount' => $amount,
            'status' => PaymentTransactionStatus::EXPIRED->value,
            'payload' => json_encode([
                'source' => 'recommendation_demo',
                'refund_deadline_expired' => true,
            ]),
            'completed_at' => $this->terminalTimestamp($createdAt, OrderStatus::REFUND_EXPIRED),
            'created_at' => $createdAt->copy()->addDays(4),
            'updated_at' => $this->terminalTimestamp($createdAt, OrderStatus::REFUND_EXPIRED),
        ]);
    }

    private function paymentTransactionCompletedAt(OrderStatus $terminalStatus, Carbon $createdAt): Carbon
    {
        if ($terminalStatus === OrderStatus::CANCELLED) {
            return $createdAt->copy()->addHours(13);
        }

        return $createdAt->copy()->addHours(2);
    }

    private function demoDateForTerminalStatus(Carbon $date, OrderStatus $terminalStatus): Carbon
    {
        $latestCreatedAt = match ($terminalStatus) {
            OrderStatus::REFUND_EXPIRED => now()->copy()->subDays(9)->setTime(10, 0, 0),
            OrderStatus::COMPLETED => now()->copy()->subDays(5)->setTime(10, 0, 0),
            OrderStatus::CANCELLED => now()->copy()->subDays(3)->setTime(10, 0, 0),
            default => now()->copy()->subDay()->setTime(10, 0, 0),
        };

        return $date->lte($latestCreatedAt) ? $date : $latestCreatedAt;
    }

    private function shippingFeeForOrder(Carbon $createdAt, int $quantity): float
    {
        $fees = [15000, 22000, 30000, 35000];

        return (float) $fees[($createdAt->day + $quantity) % count($fees)];
    }

    /**
     * @return array{name: string, phone: string, address: string}
     */
    private function shippingSnapshotForAccount(Account $account): array
    {
        $address = DB::table('addresses')
            ->where('account_id', $account->id)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        if ($address === null) {
            return [
                'name' => 'Recommendation Demo',
                'phone' => '0900000000',
                'address' => 'Demo address',
            ];
        }

        return [
            'name' => (string) $address->recipient_name,
            'phone' => (string) $address->recipient_phone,
            'address' => trim(sprintf(
                '%s, ward %s, district %s, province %s',
                $address->detail_address,
                $address->ward_code,
                $address->district_code,
                $address->province_code,
            )),
        ];
    }

    private function codPaidTimelineNote(float $finalAmount): string
    {
        return sprintf(
            '%s Tổng tiền: %s đ.',
            OrderStatusTransitionService::TIMELINE_NOTE_COD_PAID,
            number_format($finalAmount, 0, ',', '.'),
        );
    }

    /**
     * @return Collection<int, Carbon>
     */
    private function demoDates(): Collection
    {
        $start = Carbon::create(2026, 5, 1, 9, 0, 0);
        $end = now()->copy()->subDays(2)->setTime(16, 0, 0);
        if ($end->lt($start)) {
            $end = $start->copy()->addDays(20);
        }

        $days = max(1, $start->diffInDays($end));

        return collect(range(0, 17))->map(
            fn (int $index): Carbon => $start->copy()->addDays((int) floor($index * $days / 17))->addHours($index % 5),
        );
    }

    /**
     * @param  Collection<int, int>  $bookIds
     */
    private function refreshBookReviewAggregates(Collection $bookIds): void
    {
        foreach ($bookIds as $bookId) {
            $stats = DB::table('reviews')
                ->where('book_id', (int) $bookId)
                ->where('status', ReviewStatus::APPROVED->value)
                ->selectRaw('COUNT(*) as review_count, COALESCE(AVG(rating), 0) as average_rating')
                ->first();

            DB::table('books')
                ->where('id', (int) $bookId)
                ->update([
                    'review_count' => (int) ($stats->review_count ?? 0),
                    'average_rating' => round((float) ($stats->average_rating ?? 0), 2),
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * @param  array<string, Account>  $accounts
     */
    private function buildRecommendationCaches(
        array $accounts,
        RecommendationCandidateService $candidateService,
        RecommendationCacheService $cacheService,
    ): void {
        (new BuildPopularRecommendations())->handle($candidateService, $cacheService);

        foreach ($accounts as $account) {
            (new BuildUserRecommendations((int) $account->id))->handle($candidateService, $cacheService);
        }
    }

    /**
     * @param  array<string, Account>  $accounts
     */
    private function printSummary(array $accounts, string $password): void
    {
        $this->info('Recommendation demo data seeded and recommendation caches warmed.');
        $this->newLine();
        $this->line('Demo accounts:');

        foreach ($accounts as $key => $account) {
            $strategy = $key === 'cold' ? 'popular fallback' : 'content_based';
            $this->line(sprintf(
                '- %s | id=%d | %s | expected=%s',
                $account->email,
                $account->id,
                $password,
                $strategy,
            ));
        }

        $this->newLine();
        $this->line('Demo API calls:');
        $this->line('- Guest: GET /api/v1/recommendations?limit=10 -> strategy popular');
        $this->line('- Demo A/B after login: GET /api/v1/recommendations?limit=10 -> strategy content_based');
        $this->line('- Cold account after login: GET /api/v1/recommendations?limit=10 -> strategy popular');
    }
}
