<?php

use App\Enums\Account\AccountRole;
use App\Models\Account;
use App\Models\Book;
use App\Models\Inventory;
use App\Models\ShippingMethod;
use App\Models\ShippingRate;
use App\Models\Warehouse;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutMiddleware(VerifyCsrfToken::class);
    $this->disableCookieEncryption();
    $this->withCredentials();
    $this->withHeader('Origin', 'http://localhost');
});

function loginAsAccount(Account $account): void
{
    test()->postJson('/api/v1/auth/login', [
        'email' => $account->email,
        'password' => 'password',
    ])->assertOk();
}

function inactiveSessionBookWithStock(): Book
{
    $book = Book::factory()->create();
    Inventory::factory()->create([
        'book_id' => $book->id,
        'warehouse_id' => Warehouse::factory(),
        'quantity' => 10,
        'reserved_quantity' => 0,
    ]);

    return $book;
}

function inactiveSessionShippingMethod(): ShippingMethod
{
    $method = ShippingMethod::query()->create([
        'name' => 'Standard',
        'description' => null,
        'is_active' => true,
    ]);

    ShippingRate::query()->create([
        'shipping_method_id' => $method->id,
        'province_code' => '01',
        'base_fee' => 30000,
    ]);

    return $method;
}

test('active logged-in user can access account profile', function (): void {
    $account = Account::factory()->create([
        'email' => 'active@example.com',
        'is_active' => true,
    ]);

    loginAsAccount($account);

    $this->getJson('/api/v1/account/profile')
        ->assertOk()
        ->assertJsonPath('data.email', 'active@example.com');
});

test('deactivated session user is blocked from profile and logged out', function (): void {
    $account = Account::factory()->create([
        'email' => 'locked@example.com',
        'is_active' => true,
    ]);

    loginAsAccount($account);

    $account->update(['is_active' => false]);

    $this->getJson('/api/v1/account/profile')
        ->assertForbidden()
        ->assertJsonPath('message', 'Tài khoản đã bị khóa hoặc chưa được kích hoạt.');

    $this->app->make('auth')->forgetGuards();
    $this->flushSession();

    $this->getJson('/api/v1/account/profile')->assertUnauthorized();
});

test('deactivated session user is blocked from checkout and shipping quote', function (): void {
    $account = Account::factory()->create(['is_active' => true]);
    $book = inactiveSessionBookWithStock();
    $ship = inactiveSessionShippingMethod();

    loginAsAccount($account);

    $this->postJson('/api/v1/cart/items', [
        'book_id' => $book->id,
        'quantity' => 1,
    ])->assertCreated();

    $account->update(['is_active' => false]);

    $this->postJson('/api/v1/checkout', [
        'idempotency_key' => '00000000-0000-4000-8000-000000000001',
        'payment_method' => 'cod',
        'shipping_method_id' => $ship->id,
        'shipping' => [
            'recipient_name' => 'Nguyen Van A',
            'recipient_phone' => '0900000000',
            'province_code' => '01',
            'ward_code' => '00070',
            'detail_address' => '1 Test St',
        ],
        'pricing_expectations' => checkoutPricingExpectationsForBook($book),
    ])
        ->assertForbidden()
        ->assertJsonPath('message', 'Tài khoản đã bị khóa hoặc chưa được kích hoạt.');

    $this->postJson('/api/v1/shipping/quote', [
        'shipping_method_id' => $ship->id,
        'province_code' => '01',
    ])
        ->assertForbidden()
        ->assertJsonPath('message', 'Tài khoản đã bị khóa hoặc chưa được kích hoạt.');
});

test('deactivated session user is blocked from review eligibility', function (): void {
    $account = Account::factory()->create(['is_active' => true]);
    $book = Book::factory()->create();

    loginAsAccount($account);
    $account->update(['is_active' => false]);

    $this->getJson("/api/v1/books/{$book->slug}/review-eligibility")
        ->assertForbidden()
        ->assertJsonPath('message', 'Tài khoản đã bị khóa hoặc chưa được kích hoạt.');
});

test('deactivated session user is blocked from cart and subsequent cart acts as guest', function (): void {
    $account = Account::factory()->create(['is_active' => true]);
    $book = inactiveSessionBookWithStock();

    loginAsAccount($account);

    $this->postJson('/api/v1/cart/items', [
        'book_id' => $book->id,
        'quantity' => 2,
    ])->assertCreated();

    $account->update(['is_active' => false]);

    $this->getJson('/api/v1/cart')
        ->assertForbidden()
        ->assertJsonPath('message', 'Tài khoản đã bị khóa hoặc chưa được kích hoạt.');

    $this->getJson('/api/v1/cart')
        ->assertOk()
        ->assertJsonPath('data.items', []);
});
