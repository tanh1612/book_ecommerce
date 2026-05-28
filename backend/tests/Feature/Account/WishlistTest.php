<?php

use App\Models\Account;
use App\Models\Book;
use App\Models\Wishlist;
use App\Jobs\Recommendation\BuildUserRecommendations;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutMiddleware(VerifyCsrfToken::class);
});

test('guest cannot list wishlist', function (): void {
    $this->getJson('/api/v1/account/wishlist')->assertUnauthorized();
});

test('guest cannot add to wishlist', function (): void {
    $book = Book::factory()->create();

    $this->postJson('/api/v1/account/wishlist/items', [
        'book_id' => $book->id,
    ])->assertUnauthorized();
});

test('guest cannot remove from wishlist', function (): void {
    $book = Book::factory()->create();

    $this->deleteJson("/api/v1/account/wishlist/items/{$book->id}")->assertUnauthorized();
});

test('authenticated user with empty wishlist gets empty list', function (): void {
    $account = Account::factory()->create();

    $this->actingAs($account, 'web');

    $this->getJson('/api/v1/account/wishlist')
        ->assertOk()
        ->assertJsonPath('data', []);
});

test('authenticated user lists only own wishlist books', function (): void {
    $owner = Account::factory()->create();
    $other = Account::factory()->create();

    $ownBook = Book::factory()->create(['name' => 'Own Book']);
    $otherBook = Book::factory()->create(['name' => 'Other Book']);

    Wishlist::factory()->create([
        'account_id' => $owner->id,
        'book_id' => $ownBook->id,
    ]);

    Wishlist::factory()->create([
        'account_id' => $other->id,
        'book_id' => $otherBook->id,
    ]);

    $this->actingAs($owner, 'web');

    $response = $this->getJson('/api/v1/account/wishlist')->assertOk();

    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($ownBook->id)->not->toContain($otherBook->id);
});

test('wishlist list orders newest first', function (): void {
    $account = Account::factory()->create();
    $olderBook = Book::factory()->create();
    $newerBook = Book::factory()->create();

    Wishlist::factory()->create([
        'account_id' => $account->id,
        'book_id' => $olderBook->id,
        'created_at' => now()->subDay(),
    ]);

    Wishlist::factory()->create([
        'account_id' => $account->id,
        'book_id' => $newerBook->id,
        'created_at' => now(),
    ]);

    $this->actingAs($account, 'web');

    $ids = collect($this->getJson('/api/v1/account/wishlist')->assertOk()->json('data'))
        ->pluck('id')
        ->all();

    expect($ids[0])->toBe($newerBook->id)
        ->and($ids[1])->toBe($olderBook->id);
});

test('authenticated user can add book to wishlist', function (): void {
    $account = Account::factory()->create();
    $book = Book::factory()->create();
    Bus::fake();

    $this->actingAs($account, 'web');

    $this->postJson('/api/v1/account/wishlist/items', [
        'book_id' => $book->id,
    ])
        ->assertOk()
        ->assertJsonPath('message', 'Đã thêm vào danh sách yêu thích.');

    $this->assertDatabaseHas('wishlists', [
        'account_id' => $account->id,
        'book_id' => $book->id,
    ]);

    Bus::assertDispatched(BuildUserRecommendations::class, fn (BuildUserRecommendations $job): bool => $job->accountId === $account->id);
});

test('adding same book twice is idempotent', function (): void {
    $account = Account::factory()->create();
    $book = Book::factory()->create();

    $this->actingAs($account, 'web');

    $this->postJson('/api/v1/account/wishlist/items', ['book_id' => $book->id])->assertOk();
    $this->postJson('/api/v1/account/wishlist/items', ['book_id' => $book->id])->assertOk();

    expect(Wishlist::query()
        ->where('account_id', $account->id)
        ->where('book_id', $book->id)
        ->count())->toBe(1);
});

test('client cannot set account_id when adding wishlist item', function (): void {
    $account = Account::factory()->create();
    $book = Book::factory()->create();

    $this->actingAs($account, 'web');

    $this->postJson('/api/v1/account/wishlist/items', [
        'book_id' => $book->id,
        'account_id' => 99999,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['account_id']);
});

test('can add inactive book to wishlist', function (): void {
    $account = Account::factory()->create();
    $book = Book::factory()->inactive()->create();

    $this->actingAs($account, 'web');

    $this->postJson('/api/v1/account/wishlist/items', [
        'book_id' => $book->id,
    ])
        ->assertOk()
        ->assertJsonPath('message', 'Đã thêm vào danh sách yêu thích.');

    $this->assertDatabaseHas('wishlists', [
        'account_id' => $account->id,
        'book_id' => $book->id,
    ]);
});

test('cannot add invalid book_id to wishlist', function (): void {
    $account = Account::factory()->create();

    $this->actingAs($account, 'web');

    $this->postJson('/api/v1/account/wishlist/items', [
        'book_id' => 999999,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['book_id']);
});

test('authenticated user can remove own wishlist item', function (): void {
    $account = Account::factory()->create();
    $book = Book::factory()->create();
    Bus::fake();

    Wishlist::factory()->create([
        'account_id' => $account->id,
        'book_id' => $book->id,
    ]);

    $this->actingAs($account, 'web');

    $this->deleteJson("/api/v1/account/wishlist/items/{$book->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('wishlists', [
        'account_id' => $account->id,
        'book_id' => $book->id,
    ]);

    Bus::assertDispatched(BuildUserRecommendations::class, fn (BuildUserRecommendations $job): bool => $job->accountId === $account->id);
});

test('removing book not in wishlist returns not found', function (): void {
    $account = Account::factory()->create();
    $book = Book::factory()->create();

    $this->actingAs($account, 'web');

    $this->deleteJson("/api/v1/account/wishlist/items/{$book->id}")->assertNotFound();
});

test('user cannot remove another users wishlist entry by book id', function (): void {
    $owner = Account::factory()->create();
    $other = Account::factory()->create();
    $book = Book::factory()->create();

    Wishlist::factory()->create([
        'account_id' => $owner->id,
        'book_id' => $book->id,
    ]);

    $this->actingAs($other, 'web');

    $this->deleteJson("/api/v1/account/wishlist/items/{$book->id}")->assertNotFound();

    $this->assertDatabaseHas('wishlists', [
        'account_id' => $owner->id,
        'book_id' => $book->id,
    ]);
});

test('wishlist list includes inactive books', function (): void {
    $account = Account::factory()->create();
    $activeBook = Book::factory()->create();
    $inactiveBook = Book::factory()->inactive()->create();

    Wishlist::factory()->create([
        'account_id' => $account->id,
        'book_id' => $activeBook->id,
    ]);

    Wishlist::factory()->create([
        'account_id' => $account->id,
        'book_id' => $inactiveBook->id,
    ]);

    $this->actingAs($account, 'web');

    $ids = collect($this->getJson('/api/v1/account/wishlist')->assertOk()->json('data'))
        ->pluck('id')
        ->all();

    expect($ids)->toContain($activeBook->id)->toContain($inactiveBook->id);
});
