<?php

use App\Models\Account;
use App\Models\Address;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutMiddleware(VerifyCsrfToken::class);
    Cache::flush();
    Http::preventStrayRequests();
});

function fakeSuccessfulNewFullAddress(string $provinceCode = '01', string $wardCode = '00070'): void
{
    Http::fake([
        '*new-full-address*' => Http::response([
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
        ], 200),
    ]);
}

test('guest cannot create address', function (): void {
    fakeSuccessfulNewFullAddress();

    $this->postJson('/api/v1/account/addresses', [
        'recipient_name' => 'A',
        'recipient_phone' => '0909123456',
        'province_code' => '01',
        'ward_code' => '00070',
        'detail_address' => '15 Test',
    ])->assertUnauthorized();
});

test('authenticated user can create address', function (): void {
    fakeSuccessfulNewFullAddress();

    $account = Account::factory()->create();

    $this->actingAs($account, 'web');

    $this->postJson('/api/v1/account/addresses', [
        'recipient_name' => 'Nguyen Van A',
        'recipient_phone' => '0909123456',
        'province_code' => '01',
        'ward_code' => '00070',
        'detail_address' => '15 Nguyen Van A',
    ])
        ->assertCreated()
        ->assertJsonPath('data.recipient_name', 'Nguyen Van A')
        ->assertJsonPath('data.province_code', '01')
        ->assertJsonPath('data.ward_code', '00070')
        ->assertJsonPath('data.district_code', null)
        ->assertJsonPath('data.is_default', true);

    $this->assertDatabaseHas('addresses', [
        'account_id' => $account->id,
        'recipient_name' => 'Nguyen Van A',
        'province_code' => '01',
        'ward_code' => '00070',
        'district_code' => null,
        'is_default' => true,
    ]);
});

test('client cannot set account_id or district_code', function (): void {
    fakeSuccessfulNewFullAddress();

    $account = Account::factory()->create();

    $this->actingAs($account, 'web');

    $this->postJson('/api/v1/account/addresses', [
        'recipient_name' => 'A',
        'recipient_phone' => '0909123456',
        'province_code' => '01',
        'ward_code' => '00070',
        'detail_address' => '15 Test',
        'account_id' => 99999,
        'district_code' => '001',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['account_id', 'district_code']);
});

test('ward not matching province from upstream is rejected', function (): void {
    Http::fake([
        '*new-full-address*' => Http::response([
            'success' => true,
            'data' => [
                'province' => ['code' => '01', 'name' => 'Hà Nội', 'type' => 'Thành phố'],
                'ward' => ['code' => '99999', 'name' => 'Other', 'type' => 'Phường', 'province_code' => '01'],
            ],
        ], 200),
    ]);

    $account = Account::factory()->create();

    $this->actingAs($account, 'web');

    $this->postJson('/api/v1/account/addresses', [
        'recipient_name' => 'A',
        'recipient_phone' => '0909123456',
        'province_code' => '01',
        'ward_code' => '00070',
        'detail_address' => '15 Test',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ward_code']);
});

test('first address is default even when is_default omitted', function (): void {
    fakeSuccessfulNewFullAddress();

    $account = Account::factory()->create();

    $this->actingAs($account, 'web');

    $this->postJson('/api/v1/account/addresses', [
        'recipient_name' => 'A',
        'recipient_phone' => '0909123456',
        'province_code' => '01',
        'ward_code' => '00070',
        'detail_address' => '15 Test',
    ])
        ->assertCreated()
        ->assertJsonPath('data.is_default', true);
});

test('creating new default address unsets previous default', function (): void {
    Http::fake([
        '*new-full-address*' => Http::response([
            'success' => true,
            'data' => [
                'province' => ['code' => '01', 'name' => 'Hà Nội', 'type' => 'Thành phố'],
                'ward' => [
                    'code' => '00071',
                    'name' => 'W',
                    'type' => 'Phường',
                    'province_code' => '01',
                ],
            ],
        ], 200),
    ]);

    $account = Account::factory()->create();

    Address::query()->create([
        'account_id' => $account->id,
        'recipient_name' => 'Old',
        'recipient_phone' => '0909111111',
        'province_code' => '01',
        'district_code' => null,
        'ward_code' => '00070',
        'detail_address' => 'Old line',
        'is_default' => true,
    ]);

    $this->actingAs($account, 'web');

    $this->postJson('/api/v1/account/addresses', [
        'recipient_name' => 'New',
        'recipient_phone' => '0909222222',
        'province_code' => '01',
        'ward_code' => '00071',
        'detail_address' => 'New line',
        'is_default' => true,
    ])->assertCreated();

    expect(Address::query()->where('account_id', $account->id)->where('is_default', true)->count())->toBe(1)
        ->and(Address::query()->where('account_id', $account->id)->where('ward_code', '00070')->value('is_default'))->toBeFalse()
        ->and(Address::query()->where('account_id', $account->id)->where('ward_code', '00071')->value('is_default'))->toBeTrue();
});

test('address create returns 503 when location API fails', function (): void {
    Http::fake([
        '*new-full-address*' => Http::response('', 500),
    ]);

    $account = Account::factory()->create();

    $this->actingAs($account, 'web');

    $this->postJson('/api/v1/account/addresses', [
        'recipient_name' => 'A',
        'recipient_phone' => '0909123456',
        'province_code' => '01',
        'ward_code' => '00070',
        'detail_address' => '15 Test',
    ])->assertStatus(503);
});
