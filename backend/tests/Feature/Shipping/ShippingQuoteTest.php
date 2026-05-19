<?php

use App\Models\Account;
use App\Models\Address;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutMiddleware(VerifyCsrfToken::class);
});

function quoteShippingMethod(string $provinceCode = '01'): ShippingMethod
{
    $method = ShippingMethod::query()->create([
        'name' => 'Giao hàng tiêu chuẩn',
        'description' => null,
        'is_active' => true,
    ]);

    ShippingRate::query()->create([
        'shipping_method_id' => $method->id,
        'province_code' => $provinceCode,
        'base_fee' => 24000,
    ]);

    ShippingRate::query()->create([
        'shipping_method_id' => $method->id,
        'province_code' => null,
        'base_fee' => 50000,
    ]);

    return $method;
}

test('guest cannot request shipping quote', function (): void {
    $this->postJson('/api/v1/shipping/quote', [])->assertUnauthorized();
});

test('shipping quote by province_code returns method and fee', function (): void {
    $account = Account::factory()->create();
    $method = quoteShippingMethod('01');

    $this->actingAs($account)->postJson('/api/v1/shipping/quote', [
        'shipping_method_id' => $method->id,
        'province_code' => '01',
    ])->assertOk()
        ->assertJsonPath('data.shipping_method.id', $method->id)
        ->assertJsonPath('data.shipping_method.name', 'Giao hàng tiêu chuẩn')
        ->assertJsonPath('data.shipping_fee', 24000);
});

test('shipping quote by address_id uses address province', function (): void {
    $account = Account::factory()->create();
    $method = quoteShippingMethod('01');

    $address = Address::query()->create([
        'account_id' => $account->id,
        'recipient_name' => 'A',
        'recipient_phone' => '0900000000',
        'province_code' => '01',
        'district_code' => null,
        'ward_code' => '00070',
        'detail_address' => '1 St',
        'is_default' => true,
    ]);

    $this->actingAs($account)->postJson('/api/v1/shipping/quote', [
        'shipping_method_id' => $method->id,
        'address_id' => $address->id,
    ])->assertOk()
        ->assertJsonPath('data.shipping_fee', 24000);
});

test('shipping quote uses fallback rate for other provinces', function (): void {
    $account = Account::factory()->create();
    $method = quoteShippingMethod('01');

    $this->actingAs($account)->postJson('/api/v1/shipping/quote', [
        'shipping_method_id' => $method->id,
        'province_code' => '22',
    ])->assertOk()
        ->assertJsonPath('data.shipping_fee', 50000);
});

test('shipping quote rejects address_id and province_code together', function (): void {
    $account = Account::factory()->create();
    $method = quoteShippingMethod('01');

    $address = Address::query()->create([
        'account_id' => $account->id,
        'recipient_name' => 'A',
        'recipient_phone' => '0900000000',
        'province_code' => '01',
        'district_code' => null,
        'ward_code' => '00070',
        'detail_address' => '1 St',
        'is_default' => true,
    ]);

    $this->actingAs($account)->postJson('/api/v1/shipping/quote', [
        'shipping_method_id' => $method->id,
        'address_id' => $address->id,
        'province_code' => '01',
    ])->assertStatus(422);
});

test('shipping quote rejects inactive shipping method', function (): void {
    $account = Account::factory()->create();
    $method = ShippingMethod::query()->create([
        'name' => 'Off',
        'description' => null,
        'is_active' => false,
    ]);

    $this->actingAs($account)->postJson('/api/v1/shipping/quote', [
        'shipping_method_id' => $method->id,
        'province_code' => '01',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['shipping_method_id']);
});
