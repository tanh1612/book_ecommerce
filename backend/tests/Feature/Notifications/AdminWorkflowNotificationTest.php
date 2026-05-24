<?php

use App\Enums\Account\AccountRole;
use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentMethod;
use App\Enums\Order\PaymentStatus;
use App\Models\Account;
use App\Models\Book;
use App\Models\CartItem;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentTransaction;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\Warehouse;
use App\Notifications\Order\NewOrderNeedsProcessingNotification;
use App\Notifications\Review\NewReviewPendingApprovalNotification;
use App\Services\Account\CreateReviewService;
use App\Services\Payment\VnPayService;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutMiddleware(VerifyCsrfToken::class);

    Http::fake(function (\Illuminate\Http\Client\Request $request) {
        if (! str_contains($request->url(), 'new-full-address')) {
            return Http::response([], 404);
        }

        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $provinceCode = (string) ($query['provinceCode'] ?? '01');
        $wardCode = (string) ($query['wardCode'] ?? '00070');

        return Http::response([
            'success' => true,
            'data' => [
                'province' => [
                    'code' => $provinceCode,
                    'name' => 'Hà Nội',
                    'type' => 'Thành phố',
                ],
                'ward' => [
                    'code' => $wardCode,
                    'name' => 'Hoàn Kiếm',
                    'type' => 'Phường',
                    'province_code' => $provinceCode,
                ],
            ],
        ], 200);
    });

    config([
        'vnpay.tmn_code' => 'TESTTMN01',
        'vnpay.hash_secret' => 'test-secret-key-32chars-minimum',
        'vnpay.payment_url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
        'vnpay.return_url' => 'https://example.test/api/v1/payments/vnpay/return',
        'vnpay.payment_ttl_hours' => 12,
        'vnpay.version' => '2.1.0',
        'vnpay.command' => 'pay',
        'vnpay.curr_code' => 'VND',
        'vnpay.locale' => 'vn',
        'app.timezone' => 'Asia/Ho_Chi_Minh',
    ]);

    Notification::fake();
});

function adminWorkflowAdmins(): array
{
    return [
        Account::factory()->create(['role' => AccountRole::Admin, 'is_active' => true]),
        Account::factory()->create(['role' => AccountRole::Admin, 'is_active' => false]),
        Account::factory()->create(['role' => AccountRole::Customer, 'is_active' => true]),
    ];
}

function adminWorkflowBookWithStock(int $available = 10): Book
{
    $book = Book::factory()->create();

    Inventory::factory()->create([
        'book_id' => $book->id,
        'warehouse_id' => Warehouse::factory(),
        'quantity' => $available,
        'reserved_quantity' => 0,
    ]);

    return $book;
}

function adminWorkflowShippingMethod(string $provinceCode = '01'): ShippingMethod
{
    $method = ShippingMethod::query()->create([
        'name' => 'Standard',
        'description' => null,
        'is_active' => true,
    ]);

    ShippingRate::query()->create([
        'shipping_method_id' => $method->id,
        'province_code' => $provinceCode,
        'base_fee' => 30000,
    ]);

    return $method;
}

function adminWorkflowOrder(Account $account, OrderStatus $status = OrderStatus::PENDING, PaymentMethod $method = PaymentMethod::VNPAY): Order
{
    return Order::query()->create([
        'account_id' => $account->id,
        'shipping_method_id' => adminWorkflowShippingMethod()->id,
        'total_amount' => 100000,
        'shipping_fee' => 0,
        'final_amount' => 100000,
        'shipping_name' => 'Nguyen Van A',
        'shipping_phone' => '0900000000',
        'shipping_address' => '1 Test St',
        'payment_method' => $method,
        'payment_status' => $method === PaymentMethod::COD ? PaymentStatus::PAID : PaymentStatus::PENDING,
        'note' => null,
        'current_status' => $status,
    ]);
}

function adminWorkflowSignedVnPaySuccessReturn(VnPayService $service, PaymentTransaction $transaction): array
{
    $parts = parse_url((string) $transaction->payload['payment_url']);
    parse_str((string) ($parts['query'] ?? ''), $query);

    $query = array_merge($query, [
        'vnp_ResponseCode' => '00',
        'vnp_TransactionStatus' => '00',
        'vnp_TransactionNo' => '999888',
        'vnp_PayDate' => '20260518101530',
    ]);

    $ref = new ReflectionMethod(VnPayService::class, 'secureHash');
    $ref->setAccessible(true);
    $query['vnp_SecureHash'] = $ref->invoke($service, $query);

    return $query;
}

test('cod checkout notifies active admins about new order needing processing', function (): void {
    [$activeAdmin, $inactiveAdmin, $customerRoleAccount] = adminWorkflowAdmins();
    $account = Account::factory()->create();
    $book = adminWorkflowBookWithStock(5);
    $ship = adminWorkflowShippingMethod('01');

    $this->actingAs($account)->postJson('/api/v1/cart/items', [
        'book_id' => $book->id,
        'quantity' => 1,
    ])->assertCreated();

    $response = $this->actingAs($account)->postJson('/api/v1/checkout', [
        'idempotency_key' => (string) Str::uuid(),
        'payment_method' => 'cod',
        'shipping_method_id' => $ship->id,
        'shipping' => [
            'recipient_name' => 'Nguyen Van A',
            'recipient_phone' => '0900000000',
            'province_code' => '01',
            'ward_code' => '00070',
            'detail_address' => '1 Test St',
        ],
    ]);

    $response->assertCreated();
    $orderId = (int) $response->json('data.order.id');

    Notification::assertSentTo(
        $activeAdmin,
        NewOrderNeedsProcessingNotification::class,
        fn (NewOrderNeedsProcessingNotification $notification): bool => $notification->order->id === $orderId
    );
    Notification::assertNotSentTo($inactiveAdmin, NewOrderNeedsProcessingNotification::class);
    Notification::assertNotSentTo($customerRoleAccount, NewOrderNeedsProcessingNotification::class);
});

test('successful vnpay return notifies active admins when order becomes confirmed', function (): void {
    [$activeAdmin, $inactiveAdmin, $customerRoleAccount] = adminWorkflowAdmins();
    $account = Account::factory()->create();
    $order = adminWorkflowOrder($account);
    $service = app(VnPayService::class);
    $service->createPaymentUrl($order, '127.0.0.1');

    $transaction = PaymentTransaction::query()->where('order_id', $order->id)->firstOrFail();
    $result = $service->handleReturn(adminWorkflowSignedVnPaySuccessReturn($service, $transaction));

    expect($result['success'])->toBeTrue();

    Notification::assertSentTo(
        $activeAdmin,
        NewOrderNeedsProcessingNotification::class,
        fn (NewOrderNeedsProcessingNotification $notification): bool => $notification->order->id === $order->id
    );
    Notification::assertNotSentTo($inactiveAdmin, NewOrderNeedsProcessingNotification::class);
    Notification::assertNotSentTo($customerRoleAccount, NewOrderNeedsProcessingNotification::class);
});

test('creating pending review notifies active admins for moderation', function (): void {
    [$activeAdmin, $inactiveAdmin, $customerRoleAccount] = adminWorkflowAdmins();
    $account = Account::factory()->create();
    $book = Book::factory()->create();
    $order = adminWorkflowOrder($account, OrderStatus::COMPLETED, PaymentMethod::COD);
    $item = OrderItem::query()->create([
        'order_id' => $order->id,
        'book_id' => $book->id,
        'promotion_id' => null,
        'price' => 100000,
        'quantity' => 1,
        'total_price' => 100000,
        'discount_amount' => 0,
        'is_reviewed' => false,
    ]);

    $review = app(CreateReviewService::class)->create($account, $item, [
        'rating' => 4.5,
        'comment' => 'Sách hay, đóng gói tốt.',
    ]);

    Notification::assertSentTo(
        $activeAdmin,
        NewReviewPendingApprovalNotification::class,
        fn (NewReviewPendingApprovalNotification $notification): bool => $notification->review->id === $review->id
    );
    Notification::assertNotSentTo($inactiveAdmin, NewReviewPendingApprovalNotification::class);
    Notification::assertNotSentTo($customerRoleAccount, NewReviewPendingApprovalNotification::class);
});
