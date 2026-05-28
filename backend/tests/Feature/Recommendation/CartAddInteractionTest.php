<?php

use App\Enums\Recommendation\BookInteractionType;
use App\Models\Account;
use App\Models\Book;
use App\Models\BookInteractionEvent;
use App\Models\CartItem;
use App\Models\Inventory;
use App\Models\Warehouse;
use App\Services\Recommendation\InteractionTrackingService;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutMiddleware(VerifyCsrfToken::class);
    $this->disableCookieEncryption();
    $this->withCredentials();
});

function createBookWithInventory(int $available = 10): Book
{
    $book = Book::factory()->create();
    $warehouseId = (int) (Warehouse::query()->value('id') ?? Warehouse::factory()->create()->id);

    Inventory::factory()->create([
        'book_id' => $book->id,
        'warehouse_id' => $warehouseId,
        'quantity' => $available,
        'reserved_quantity' => 0,
    ]);

    return $book;
}

test('member add to cart records cart add interaction event', function (): void {
    $account = Account::factory()->create();
    $book = createBookWithInventory(5);

    $this->actingAs($account)->postJson('/api/v1/cart/items', [
        'book_id' => $book->id,
        'quantity' => 1,
    ])->assertCreated();

    $this->assertDatabaseHas('book_interaction_events', [
        'account_id' => $account->id,
        'book_id' => $book->id,
        'event_type' => BookInteractionType::CartAdd->value,
    ]);
});

test('guest add to cart does not record cart add interaction event', function (): void {
    $book = createBookWithInventory(5);

    $this->postJson('/api/v1/cart/items', [
        'book_id' => $book->id,
        'quantity' => 1,
    ])->assertCreated();

    expect(BookInteractionEvent::query()
        ->where('event_type', BookInteractionType::CartAdd)
        ->count())->toBe(0);
});

test('failed add to cart due to stock does not record cart add interaction event', function (): void {
    $account = Account::factory()->create();
    $book = createBookWithInventory(1);

    $this->actingAs($account)->postJson('/api/v1/cart/items', [
        'book_id' => $book->id,
        'quantity' => 2,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('quantity');

    expect(BookInteractionEvent::query()
        ->where('event_type', BookInteractionType::CartAdd)
        ->count())->toBe(0);
});

test('tracking failure does not rollback successful add to cart', function (): void {
    $account = Account::factory()->create();
    $book = createBookWithInventory(5);

    $this->mock(InteractionTrackingService::class, function ($mock): void {
        $mock->shouldReceive('trackCartAdd')
            ->once()
            ->andThrow(new RuntimeException('Recommendation tracking unavailable'));
    });

    $this->actingAs($account)->postJson('/api/v1/cart/items', [
        'book_id' => $book->id,
        'quantity' => 1,
    ])->assertCreated();

    $this->assertDatabaseHas('cart_items', [
        'book_id' => $book->id,
        'quantity' => 1,
    ]);

    expect(BookInteractionEvent::query()
        ->where('event_type', BookInteractionType::CartAdd)
        ->count())->toBe(0);
});

test('member add same book twice records two cart add events', function (): void {
    $account = Account::factory()->create();
    $book = createBookWithInventory(5);

    $this->actingAs($account)->postJson('/api/v1/cart/items', [
        'book_id' => $book->id,
        'quantity' => 1,
    ])->assertCreated();

    $this->actingAs($account)->postJson('/api/v1/cart/items', [
        'book_id' => $book->id,
        'quantity' => 1,
    ])->assertCreated();

    expect(BookInteractionEvent::query()
        ->where('account_id', $account->id)
        ->where('book_id', $book->id)
        ->where('event_type', BookInteractionType::CartAdd)
        ->count())->toBe(2);

    expect(CartItem::query()->where('book_id', $book->id)->firstOrFail()->quantity)->toBe(2);
});
