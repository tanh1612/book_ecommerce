<?php

use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentMethod;
use App\Enums\Order\PaymentStatus;
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
                    'id' => 25,
                    'name' => 'Ngân hàng TMCP Tiên Phong',
                    'code' => 'TPB',
                    'bin' => '970423',
                    'shortName' => 'TPBank',
                    'logo' => 'https://cdn.vietqr.io/img/TPB.png',
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

test('customer can verify valid refund bank account before submit', function (): void {
    fakeVietQrBanksResponse();
    config(['refund.verification.driver' => 'log']);

    $account = Account::factory()->create();
    $order = refundBankInfoTestOrder($account);

    PaymentTransaction::query()->create([
        'order_id' => $order->id,
        'gateway' => \App\Enums\Payment\PaymentGateway::VNPAY,
        'gateway_txn_id' => null,
        'type' => PaymentTransactionType::REFUND,
        'amount' => $order->final_amount,
        'status' => PaymentTransactionStatus::PENDING,
        'payload' => [],
    ]);

    $this->actingAs($account, 'sanctum')
        ->postJson("/api/v1/account/orders/{$order->id}/refund-bank-info/verify", [
            'bank_code' => 'VCB',
            'account_number' => '123456789',
        ])
        ->assertOk()
        ->assertJsonPath('data.verified', true)
        ->assertJsonPath('data.account_holder', 'NGUYEN VAN A')
        ->assertJsonPath('data.bank_code', 'VCB');
});

test('customer cannot verify invalid refund bank account', function (): void {
    fakeVietQrBanksResponse();
    config(['refund.verification.driver' => 'log']);

    $account = Account::factory()->create();
    $order = refundBankInfoTestOrder($account);

    PaymentTransaction::query()->create([
        'order_id' => $order->id,
        'gateway' => \App\Enums\Payment\PaymentGateway::VNPAY,
        'gateway_txn_id' => null,
        'type' => PaymentTransactionType::REFUND,
        'amount' => $order->final_amount,
        'status' => PaymentTransactionStatus::PENDING,
        'payload' => [],
    ]);

    $this->actingAs($account, 'sanctum')
        ->postJson("/api/v1/account/orders/{$order->id}/refund-bank-info/verify", [
            'bank_code' => 'VCB',
            'account_number' => '12340000',
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Số tài khoản không tồn tại hoặc ngân hàng không hỗ trợ tra cứu.');
});

test('customer can submit refund bank info after backend re-verification', function (): void {
    fakeVietQrBanksResponse();
    config(['refund.verification.driver' => 'log']);

    $account = Account::factory()->create();
    $order = refundBankInfoTestOrder($account);

    PaymentTransaction::query()->create([
        'order_id' => $order->id,
        'gateway' => \App\Enums\Payment\PaymentGateway::VNPAY,
        'gateway_txn_id' => null,
        'type' => PaymentTransactionType::REFUND,
        'amount' => $order->final_amount,
        'status' => PaymentTransactionStatus::PENDING,
        'payload' => [],
    ]);

    $this->actingAs($account, 'sanctum')
        ->postJson("/api/v1/account/orders/{$order->id}/refund-bank-info", [
            'bank_code' => 'VCB',
            'account_number' => '987654321',
        ])
        ->assertCreated()
        ->assertJsonPath('data.manual_refund.needs_bank_info', false)
        ->assertJsonPath('data.manual_refund.bank_info.verified', true)
        ->assertJsonPath('data.manual_refund.bank_info.account_holder', 'NGUYEN VAN A');

    $txn = PaymentTransaction::query()->where('order_id', $order->id)->firstOrFail();
    expect($txn->payload['bank_info']['account_number'] ?? null)->toBe('987654321')
        ->and($txn->payload['bank_info']['verification']['status'] ?? null)->toBe('verified');
});

test('customer cannot submit refund bank info twice', function (): void {
    fakeVietQrBanksResponse();
    config(['refund.verification.driver' => 'log']);

    $account = Account::factory()->create();
    $order = refundBankInfoTestOrder($account);

    PaymentTransaction::query()->create([
        'order_id' => $order->id,
        'gateway' => \App\Enums\Payment\PaymentGateway::VNPAY,
        'gateway_txn_id' => null,
        'type' => PaymentTransactionType::REFUND,
        'amount' => $order->final_amount,
        'status' => PaymentTransactionStatus::PENDING,
        'payload' => [],
    ]);

    $payload = ['bank_code' => 'VCB', 'account_number' => '987654321'];

    $this->actingAs($account, 'sanctum')
        ->postJson("/api/v1/account/orders/{$order->id}/refund-bank-info", $payload)
        ->assertCreated();

    $this->actingAs($account, 'sanctum')
        ->postJson("/api/v1/account/orders/{$order->id}/refund-bank-info", $payload)
        ->assertStatus(422);
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

    Http::assertSentCount(1);
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

test('customer cannot verify bank without lookup support', function (): void {
    fakeVietQrBanksResponse();
    config(['refund.verification.driver' => 'log']);

    $account = Account::factory()->create();
    $order = refundBankInfoTestOrder($account);

    PaymentTransaction::query()->create([
        'order_id' => $order->id,
        'gateway' => \App\Enums\Payment\PaymentGateway::VNPAY,
        'gateway_txn_id' => null,
        'type' => PaymentTransactionType::REFUND,
        'amount' => $order->final_amount,
        'status' => PaymentTransactionStatus::PENDING,
        'payload' => [],
    ]);

    $this->actingAs($account, 'sanctum')
        ->postJson("/api/v1/account/orders/{$order->id}/refund-bank-info/verify", [
            'bank_code' => 'NOLOOKUP',
            'account_number' => '123456789',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['bank_code']);
});

test('appotapay verifier returns account holder on success', function (): void {
    fakeVietQrBanksResponse();
    config([
        'refund.verification.driver' => 'appotapay',
        'refund.verification.appotapay.base_url' => 'https://gateway.test.appotapay.com',
        'refund.verification.appotapay.partner_code' => 'PARTNER',
        'refund.verification.appotapay.api_key' => 'api-key',
        'refund.verification.appotapay.secret_key' => 'secret-key',
    ]);

    $account = Account::factory()->create();
    $order = refundBankInfoTestOrder($account);

    PaymentTransaction::query()->create([
        'order_id' => $order->id,
        'gateway' => \App\Enums\Payment\PaymentGateway::VNPAY,
        'gateway_txn_id' => null,
        'type' => PaymentTransactionType::REFUND,
        'amount' => $order->final_amount,
        'status' => PaymentTransactionStatus::PENDING,
        'payload' => [],
    ]);

    Http::fake([
        'https://gateway.test.appotapay.com/api/v1/service/transfer/bank/account/info' => Http::response([
            'errorCode' => 0,
            'message' => 'Thành công',
            'accountInfo' => [
                'accountNo' => '13210013240000',
                'accountName' => 'TRAN VAN B',
                'bankCode' => 'TPBANK',
                'accountType' => 'account',
            ],
            'signature' => 'ignored-in-test',
        ]),
    ]);

    $this->actingAs($account, 'sanctum')
        ->postJson("/api/v1/account/orders/{$order->id}/refund-bank-info/verify", [
            'bank_code' => 'TPB',
            'account_number' => '13210013240000',
        ])
        ->assertOk()
        ->assertJsonPath('data.account_holder', 'TRAN VAN B')
        ->assertJsonPath('data.bank_code', 'TPB');

    Http::assertSent(function ($request): bool {
        $body = $request->data();

        return str_contains($request->url(), '/api/v1/service/transfer/bank/account/info')
            && ($body['bankCode'] ?? null) === 'TPBANK'
            && ($body['accountNo'] ?? null) === '13210013240000'
            && isset($body['signature']);
    });
});

test('verify refund bank info returns 503 when bank catalog is unavailable', function (): void {
    Cache::flush();

    Http::fake(function ($request) {
        if (str_contains($request->url(), 'api.vietqr.io/v2/banks')) {
            return Http::response([], 500);
        }
    });

    config(['refund.verification.driver' => 'log']);

    $account = Account::factory()->create();
    $order = refundBankInfoTestOrder($account);

    PaymentTransaction::query()->create([
        'order_id' => $order->id,
        'gateway' => \App\Enums\Payment\PaymentGateway::VNPAY,
        'gateway_txn_id' => null,
        'type' => PaymentTransactionType::REFUND,
        'amount' => $order->final_amount,
        'status' => PaymentTransactionStatus::PENDING,
        'payload' => [],
    ]);

    $this->actingAs($account, 'sanctum')
        ->postJson("/api/v1/account/orders/{$order->id}/refund-bank-info/verify", [
            'bank_code' => 'VCB',
            'account_number' => '123456789',
        ])
        ->assertStatus(503);
});

test('customer cannot verify refund bank info without pending refund transaction', function (): void {
    fakeVietQrBanksResponse();
    config(['refund.verification.driver' => 'log']);

    $account = Account::factory()->create();
    $order = refundBankInfoTestOrder($account);

    $this->actingAs($account, 'sanctum')
        ->postJson("/api/v1/account/orders/{$order->id}/refund-bank-info/verify", [
            'bank_code' => 'VCB',
            'account_number' => '123456789',
        ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Không thể xác minh thông tin hoàn tiền cho đơn này.');
});
