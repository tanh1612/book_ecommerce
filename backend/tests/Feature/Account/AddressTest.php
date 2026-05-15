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

test('guest cannot list addresses', function (): void {
    $this->getJson('/api/v1/account/addresses')->assertUnauthorized();
});

test('authenticated user with no addresses gets empty list', function (): void {
    $account = Account::factory()->create();

    $this->actingAs($account, 'web');

    $this->getJson('/api/v1/account/addresses')
        ->assertOk()
        ->assertJsonPath('data', []);
});

test('authenticated user lists only own addresses', function (): void {
    $owner = Account::factory()->create();
    $other = Account::factory()->create();

    $own = Address::query()->create([
        'account_id' => $owner->id,
        'recipient_name' => 'Mine',
        'recipient_phone' => '0909111111',
        'province_code' => '01',
        'district_code' => null,
        'ward_code' => '00070',
        'detail_address' => 'My line',
        'is_default' => true,
    ]);

    $otherAddress = Address::query()->create([
        'account_id' => $other->id,
        'recipient_name' => 'Theirs',
        'recipient_phone' => '0909222222',
        'province_code' => '01',
        'district_code' => null,
        'ward_code' => '00070',
        'detail_address' => 'Other line',
        'is_default' => true,
    ]);

    $this->actingAs($owner, 'web');

    $response = $this->getJson('/api/v1/account/addresses')->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($own->id)->not->toContain($otherAddress->id);
});

test('address list orders default first then newest', function (): void {
    $account = Account::factory()->create();

    $older = Address::query()->create([
        'account_id' => $account->id,
        'recipient_name' => 'Old',
        'recipient_phone' => '0909111111',
        'province_code' => '01',
        'district_code' => null,
        'ward_code' => '00070',
        'detail_address' => 'Old line',
        'is_default' => false,
    ]);

    $newer = Address::query()->create([
        'account_id' => $account->id,
        'recipient_name' => 'New',
        'recipient_phone' => '0909222222',
        'province_code' => '01',
        'district_code' => null,
        'ward_code' => '00070',
        'detail_address' => 'New line',
        'is_default' => false,
    ]);

    $defaultAddr = Address::query()->create([
        'account_id' => $account->id,
        'recipient_name' => 'Def',
        'recipient_phone' => '0909333333',
        'province_code' => '01',
        'district_code' => null,
        'ward_code' => '00070',
        'detail_address' => 'Default line',
        'is_default' => true,
    ]);

    $this->actingAs($account, 'web');

    $ids = collect($this->getJson('/api/v1/account/addresses')->assertOk()->json('data'))
        ->pluck('id')
        ->all();

    expect($ids[0])->toBe($defaultAddr->id)
        ->and($ids[1])->toBe($newer->id)
        ->and($ids[2])->toBe($older->id);
});

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

test('guest cannot update or delete address', function (): void {
    fakeSuccessfulNewFullAddress();

    $this->patchJson('/api/v1/account/addresses/1', [
        'recipient_name' => 'X',
        'recipient_phone' => '0909123456',
        'province_code' => '01',
        'ward_code' => '00070',
        'detail_address' => 'Line',
    ])->assertUnauthorized();

    $this->deleteJson('/api/v1/account/addresses/1')->assertUnauthorized();
});

test('user cannot update or delete another users address', function (): void {
    fakeSuccessfulNewFullAddress();

    $owner = Account::factory()->create();
    $other = Account::factory()->create();

    $address = Address::query()->create([
        'account_id' => $owner->id,
        'recipient_name' => 'Owner',
        'recipient_phone' => '0909111111',
        'province_code' => '01',
        'district_code' => null,
        'ward_code' => '00070',
        'detail_address' => 'Line',
        'is_default' => true,
    ]);

    $this->actingAs($other, 'web');

    $this->patchJson("/api/v1/account/addresses/{$address->id}", [
        'recipient_name' => 'Hacker',
        'recipient_phone' => '0909222222',
        'province_code' => '01',
        'ward_code' => '00070',
        'detail_address' => 'Other',
    ])->assertNotFound();

    $this->deleteJson("/api/v1/account/addresses/{$address->id}")->assertNotFound();
});

test('authenticated user can update address with full payload', function (): void {
    fakeSuccessfulNewFullAddress();

    $account = Account::factory()->create();

    $address = Address::query()->create([
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

    $this->patchJson("/api/v1/account/addresses/{$address->id}", [
        'recipient_name' => 'New Name',
        'recipient_phone' => '0987654321',
        'province_code' => '01',
        'ward_code' => '00070',
        'detail_address' => 'New line',
    ])
        ->assertOk()
        ->assertJsonPath('data.recipient_name', 'New Name')
        ->assertJsonPath('data.recipient_phone', '0987654321')
        ->assertJsonPath('data.detail_address', 'New line')
        ->assertJsonPath('data.is_default', true);

    $this->assertDatabaseHas('addresses', [
        'id' => $address->id,
        'recipient_name' => 'New Name',
        'recipient_phone' => '0987654321',
        'detail_address' => 'New line',
        'is_default' => true,
    ]);
});

test('address update rejects missing required fields', function (): void {
    fakeSuccessfulNewFullAddress();

    $account = Account::factory()->create();

    $address = Address::query()->create([
        'account_id' => $account->id,
        'recipient_name' => 'A',
        'recipient_phone' => '0909111111',
        'province_code' => '01',
        'district_code' => null,
        'ward_code' => '00070',
        'detail_address' => 'Line',
        'is_default' => true,
    ]);

    $this->actingAs($account, 'web');

    $this->patchJson("/api/v1/account/addresses/{$address->id}", [
        'recipient_name' => 'B',
        'recipient_phone' => '0909222222',
        'province_code' => '01',
        'ward_code' => '00070',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['detail_address']);
});

test('address update rejects ward mismatch from upstream', function (): void {
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

    $address = Address::query()->create([
        'account_id' => $account->id,
        'recipient_name' => 'A',
        'recipient_phone' => '0909111111',
        'province_code' => '01',
        'district_code' => null,
        'ward_code' => '00070',
        'detail_address' => 'Line',
        'is_default' => false,
    ]);

    $this->actingAs($account, 'web');

    $this->patchJson("/api/v1/account/addresses/{$address->id}", [
        'recipient_name' => 'A',
        'recipient_phone' => '0909111111',
        'province_code' => '01',
        'ward_code' => '00070',
        'detail_address' => 'Line',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ward_code']);
});

test('address update returns 503 when location API fails', function (): void {
    Http::fake([
        '*new-full-address*' => Http::response('', 500),
    ]);

    $account = Account::factory()->create();

    $address = Address::query()->create([
        'account_id' => $account->id,
        'recipient_name' => 'A',
        'recipient_phone' => '0909111111',
        'province_code' => '01',
        'district_code' => null,
        'ward_code' => '00070',
        'detail_address' => 'Line',
        'is_default' => true,
    ]);

    $this->actingAs($account, 'web');

    $this->patchJson("/api/v1/account/addresses/{$address->id}", [
        'recipient_name' => 'A',
        'recipient_phone' => '0909111111',
        'province_code' => '01',
        'ward_code' => '00070',
        'detail_address' => 'Line',
    ])->assertStatus(503);
});

test('patch is_default true unsets other default', function (): void {
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

    $defaultAddr = Address::query()->create([
        'account_id' => $account->id,
        'recipient_name' => 'D',
        'recipient_phone' => '0909111111',
        'province_code' => '01',
        'district_code' => null,
        'ward_code' => '00070',
        'detail_address' => 'Default line',
        'is_default' => true,
    ]);

    $other = Address::query()->create([
        'account_id' => $account->id,
        'recipient_name' => 'O',
        'recipient_phone' => '0909222222',
        'province_code' => '01',
        'district_code' => null,
        'ward_code' => '00071',
        'detail_address' => 'Other line',
        'is_default' => false,
    ]);

    $this->actingAs($account, 'web');

    $this->patchJson("/api/v1/account/addresses/{$other->id}", [
        'recipient_name' => 'O',
        'recipient_phone' => '0909222222',
        'province_code' => '01',
        'ward_code' => '00071',
        'detail_address' => 'Other line',
        'is_default' => true,
    ])->assertOk();

    expect(Address::query()->find($defaultAddr->id)?->is_default)->toBeFalse()
        ->and(Address::query()->find($other->id)?->is_default)->toBeTrue();
});

test('patch is_default false on current default returns 422 with message', function (): void {
    fakeSuccessfulNewFullAddress();

    $account = Account::factory()->create();

    $address = Address::query()->create([
        'account_id' => $account->id,
        'recipient_name' => 'A',
        'recipient_phone' => '0909111111',
        'province_code' => '01',
        'district_code' => null,
        'ward_code' => '00070',
        'detail_address' => 'Line',
        'is_default' => true,
    ]);

    $this->actingAs($account, 'web');

    $this->patchJson("/api/v1/account/addresses/{$address->id}", [
        'recipient_name' => 'A',
        'recipient_phone' => '0909111111',
        'province_code' => '01',
        'ward_code' => '00070',
        'detail_address' => 'Line',
        'is_default' => false,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['is_default'])
        ->assertJsonFragment(['Bạn phải đặt địa chỉ khác làm mặc định trước.']);
});

test('patch is_default true on already default address returns 200', function (): void {
    fakeSuccessfulNewFullAddress();

    $account = Account::factory()->create();

    $address = Address::query()->create([
        'account_id' => $account->id,
        'recipient_name' => 'A',
        'recipient_phone' => '0909111111',
        'province_code' => '01',
        'district_code' => null,
        'ward_code' => '00070',
        'detail_address' => 'Line',
        'is_default' => true,
    ]);

    $this->actingAs($account, 'web');

    $this->patchJson("/api/v1/account/addresses/{$address->id}", [
        'recipient_name' => 'Updated',
        'recipient_phone' => '0909111111',
        'province_code' => '01',
        'ward_code' => '00070',
        'detail_address' => 'Line',
        'is_default' => true,
    ])
        ->assertOk()
        ->assertJsonPath('data.is_default', true)
        ->assertJsonPath('data.recipient_name', 'Updated');
});

test('user can delete non-default address', function (): void {
    $account = Account::factory()->create();

    $toDelete = Address::query()->create([
        'account_id' => $account->id,
        'recipient_name' => 'X',
        'recipient_phone' => '0909111111',
        'province_code' => '01',
        'district_code' => null,
        'ward_code' => '00070',
        'detail_address' => 'Line',
        'is_default' => false,
    ]);

    Address::query()->create([
        'account_id' => $account->id,
        'recipient_name' => 'D',
        'recipient_phone' => '0909222222',
        'province_code' => '01',
        'district_code' => null,
        'ward_code' => '00070',
        'detail_address' => 'Default',
        'is_default' => true,
    ]);

    $this->actingAs($account, 'web');

    $this->deleteJson("/api/v1/account/addresses/{$toDelete->id}")
        ->assertNoContent();

    expect(Address::query()->whereKey($toDelete->id)->exists())->toBeFalse();
});

test('user cannot delete default address', function (): void {
    $account = Account::factory()->create();

    $defaultAddr = Address::query()->create([
        'account_id' => $account->id,
        'recipient_name' => 'D',
        'recipient_phone' => '0909111111',
        'province_code' => '01',
        'district_code' => null,
        'ward_code' => '00070',
        'detail_address' => 'Line',
        'is_default' => true,
    ]);

    $this->actingAs($account, 'web');

    $this->deleteJson("/api/v1/account/addresses/{$defaultAddr->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['address'])
        ->assertJsonFragment(['Bạn phải đặt địa chỉ khác làm mặc định trước khi xóa địa chỉ này.']);

    expect(Address::query()->whereKey($defaultAddr->id)->exists())->toBeTrue();
});

test('update rejects prohibited account_id and district_code', function (): void {
    fakeSuccessfulNewFullAddress();

    $account = Account::factory()->create();

    $address = Address::query()->create([
        'account_id' => $account->id,
        'recipient_name' => 'A',
        'recipient_phone' => '0909111111',
        'province_code' => '01',
        'district_code' => null,
        'ward_code' => '00070',
        'detail_address' => 'Line',
        'is_default' => true,
    ]);

    $this->actingAs($account, 'web');

    $this->patchJson("/api/v1/account/addresses/{$address->id}", [
        'recipient_name' => 'A',
        'recipient_phone' => '0909111111',
        'province_code' => '01',
        'ward_code' => '00070',
        'detail_address' => 'Line',
        'account_id' => 999,
        'district_code' => '001',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['account_id', 'district_code']);
});
