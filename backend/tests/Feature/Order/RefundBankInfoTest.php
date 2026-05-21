<?php

use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentMethod;
use App\Enums\Order\PaymentStatus;
use App\Enums\Payment\PaymentGateway;
use App\Enums\Payment\PaymentTransactionStatus;
use App\Enums\Payment\PaymentTransactionType;
use App\Models\Account;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\ShippingMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function refundBankInfoTestOrder(Account $account): Order
{
    $shipping = ShippingMethod::query()->create([
        'name' => 'Test ship',
        'description' => null,
        'is_active' => true,
    ]);

    return Order::query()->create([
        'account_id' => $account->id,
        'shipping_method_id' => $shipping->id,
        'total_amount' => 250000.00,
        'shipping_fee' => 0,
        'final_amount' => 250000.00,
        'shipping_name' => 'Nguyen Van A',
        'shipping_phone' => '0901234567',
        'shipping_address' => 'Addr',
        'payment_method' => PaymentMethod::VNPAY,
        'payment_status' => PaymentStatus::REFUNDING,
        'note' => null,
        'current_status' => OrderStatus::CANCELLED,
        'refund_deadline_at' => now()->addDays(10),
    ]);
}

function fakeVietQrBanksResponse(): void
{
    Http::fake([
        'https://api.vietqr.io/v2/banks' => Http::response([
            'code' => '00',
            'desc' => 'Success',
            'data' => [
                [
                    'id' => 17,
                    'name' => 'Ngân hàng TMCP Ngoại thương Việt Nam',
                    'code' => 'VCB',
                    'bin' => '970436',
                    'shortName' => 'Vietcombank',
                    'logo' => 'https://cdn.vietqr.io/img/VCB.png',
                    'transferSupported' => 1,
                    'lookupSupported' => 1,
                ],
                [
                    'id' => 99,
                    'name' => 'Ngân hàng không tra cứu',
                    'code' => 'NOLOOKUP',
                    'bin' => '970499',
                    'shortName' => 'No Lookup Bank',
                    'logo' => null,
                    'transferSupported' => 1,
                    'lookupSupported' => 0,
                ],
            ],
        ]),
    ]);
}

function refundBankSubmitPayload(array $overrides = []): array
{
    return array_merge([
        'bank_code' => 'VCB',
        'account_number' => '987654321',
        'account_holder' => 'NGUYEN VAN A',
    ], $overrides);
}

test('customer can submit manual refund bank info', function (): void {
    fakeVietQrBanksResponse();

    $account = Account::factory()->create();
    $order = refundBankInfoTestOrder($account);

    PaymentTransaction::query()->create([
        'order_id' => $order->id,
        'gateway' => PaymentGateway::VNPAY,
        'gateway_txn_id' => null,
        'type' => PaymentTransactionType::REFUND,
        'amount' => $order->final_amount,
        'status' => PaymentTransactionStatus::PENDING,
        'payload' => [],
    ]);

    $this->actingAs($account, 'sanctum')
        ->postJson("/api/v1/account/orders/{$order->id}/refund-bank-info", refundBankSubmitPayload())
        ->assertCreated()
        ->assertJsonPath('data.order_id', $order->id)
        ->assertJsonPath('data.payment_status', PaymentStatus::REFUNDING->value)
        ->assertJsonPath('data.current_status', OrderStatus::CANCELLED->value)
        ->assertJsonPath('data.manual_refund.needs_bank_info', false)
        ->assertJsonMissingPath('data.manual_refund.bank_info.verified')
        ->assertJsonPath('data.manual_refund.bank_info.account_holder', 'NGUYEN VAN A')
        ->assertJsonMissingPath('data.items')
        ->assertJsonMissingPath('data.shipping_address')
        ->assertJsonMissingPath('data.can_cancel')
        ->assertJsonMissingPath('data.cancel_block_reason');

    $txn = PaymentTransaction::query()->where('order_id', $order->id)->firstOrFail();
    expect($txn->payload['bank_info']['account_number'] ?? null)->toBe('987654321')
        ->and($txn->payload['bank_info']['account_holder'] ?? null)->toBe('NGUYEN VAN A')
        ->and($txn->payload['bank_info']['verification']['status'] ?? null)->toBe('manual_unverified')
        ->and($txn->payload['bank_info']['verification']['provider'] ?? null)->toBe('manual');
});

test('submit refund bank info validates account holder', function (): void {
    fakeVietQrBanksResponse();

    $account = Account::factory()->create();
    $order = refundBankInfoTestOrder($account);

    PaymentTransaction::query()->create([
        'order_id' => $order->id,
        'gateway' => PaymentGateway::VNPAY,
        'gateway_txn_id' => null,
        'type' => PaymentTransactionType::REFUND,
        'amount' => $order->final_amount,
        'status' => PaymentTransactionStatus::PENDING,
        'payload' => [],
    ]);

    $this->actingAs($account, 'sanctum')
        ->postJson("/api/v1/account/orders/{$order->id}/refund-bank-info", refundBankSubmitPayload([
            'account_holder' => 'A',
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['account_holder']);
});

test('submit refund bank info validates account number format', function (): void {
    fakeVietQrBanksResponse();

    $account = Account::factory()->create();
    $order = refundBankInfoTestOrder($account);

    PaymentTransaction::query()->create([
        'order_id' => $order->id,
        'gateway' => PaymentGateway::VNPAY,
        'gateway_txn_id' => null,
        'type' => PaymentTransactionType::REFUND,
        'amount' => $order->final_amount,
        'status' => PaymentTransactionStatus::PENDING,
        'payload' => [],
    ]);

    $this->actingAs($account, 'sanctum')
        ->postJson("/api/v1/account/orders/{$order->id}/refund-bank-info", refundBankSubmitPayload([
            'account_number' => '12ab',
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['account_number']);
});

test('customer cannot submit refund bank info twice', function (): void {
    fakeVietQrBanksResponse();

    $account = Account::factory()->create();
    $order = refundBankInfoTestOrder($account);

    PaymentTransaction::query()->create([
        'order_id' => $order->id,
        'gateway' => PaymentGateway::VNPAY,
        'gateway_txn_id' => null,
        'type' => PaymentTransactionType::REFUND,
        'amount' => $order->final_amount,
        'status' => PaymentTransactionStatus::PENDING,
        'payload' => [],
    ]);

    $payload = refundBankSubmitPayload();

    $this->actingAs($account, 'sanctum')
        ->postJson("/api/v1/account/orders/{$order->id}/refund-bank-info", $payload)
        ->assertCreated();

    $this->actingAs($account, 'sanctum')
        ->postJson("/api/v1/account/orders/{$order->id}/refund-bank-info", $payload)
        ->assertStatus(422);
});

test('customer can submit refund bank info when bank catalog is temporarily down', function (): void {
    Cache::flush();

    Http::fake([
        'https://api.vietqr.io/v2/banks' => Http::response([], 500),
    ]);

    $account = Account::factory()->create();
    $order = refundBankInfoTestOrder($account);

    PaymentTransaction::query()->create([
        'order_id' => $order->id,
        'gateway' => PaymentGateway::VNPAY,
        'gateway_txn_id' => null,
        'type' => PaymentTransactionType::REFUND,
        'amount' => $order->final_amount,
        'status' => PaymentTransactionStatus::PENDING,
        'payload' => [],
    ]);

    $this->actingAs($account, 'sanctum')
        ->postJson("/api/v1/account/orders/{$order->id}/refund-bank-info", refundBankSubmitPayload([
            'bank_code' => 'MB',
        ]))
        ->assertCreated()
        ->assertJsonPath('data.manual_refund.bank_info.bank_name', 'MB');
});

test('authenticated customer can list refund banks from vietqr catalog', function (): void {
    fakeVietQrBanksResponse();
    $account = Account::factory()->create();

    $this->actingAs($account, 'sanctum')
        ->getJson('/api/v1/account/refund-banks')
        ->assertOk()
        ->assertJsonFragment([
            'code' => 'VCB',
            'short_name' => 'Vietcombank',
            'lookup_supported' => true,
            'transfer_supported' => true,
        ])
        ->assertJsonFragment(['code' => 'NOLOOKUP', 'lookup_supported' => false]);

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.vietqr.io/v2/banks');
});

test('refund banks endpoint returns backup catalog when vietqr is temporarily down', function (): void {
    fakeVietQrBanksResponse();
    $account = Account::factory()->create();

    $this->actingAs($account, 'sanctum')
        ->getJson('/api/v1/account/refund-banks')
        ->assertOk();

    Cache::forget('refund:banks:v1');

    Http::fake([
        'https://api.vietqr.io/v2/banks' => Http::response([], 500),
    ]);

    $this->actingAs($account, 'sanctum')
        ->getJson('/api/v1/account/refund-banks')
        ->assertOk()
        ->assertJsonFragment(['code' => 'VCB']);
});

test('refund banks endpoint returns 503 when vietqr fails and no cache exists', function (): void {
    Cache::flush();

    Http::fake(function ($request) {
        if (str_contains($request->url(), 'api.vietqr.io/v2/banks')) {
            return Http::response([], 500);
        }
    });

    $account = Account::factory()->create();

    $this->actingAs($account, 'sanctum')
        ->getJson('/api/v1/account/refund-banks')
        ->assertStatus(503);
});

test('customer cannot submit refund bank info without pending refund transaction', function (): void {
    fakeVietQrBanksResponse();

    $account = Account::factory()->create();
    $order = refundBankInfoTestOrder($account);

    $this->actingAs($account, 'sanctum')
        ->postJson("/api/v1/account/orders/{$order->id}/refund-bank-info", refundBankSubmitPayload())
        ->assertStatus(422)
        ->assertJsonPath('message', 'Không thể gửi thông tin hoàn tiền cho đơn này.');
});

test('customer cannot submit refund bank info for another users order', function (): void {
    fakeVietQrBanksResponse();

    $owner = Account::factory()->create();
    $other = Account::factory()->create();
    $order = refundBankInfoTestOrder($owner);

    PaymentTransaction::query()->create([
        'order_id' => $order->id,
        'gateway' => PaymentGateway::VNPAY,
        'gateway_txn_id' => null,
        'type' => PaymentTransactionType::REFUND,
        'amount' => $order->final_amount,
        'status' => PaymentTransactionStatus::PENDING,
        'payload' => [],
    ]);

    $this->actingAs($other, 'sanctum')
        ->postJson("/api/v1/account/orders/{$order->id}/refund-bank-info", refundBankSubmitPayload())
        ->assertForbidden();
});
