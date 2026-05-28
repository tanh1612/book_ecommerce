<?php

use App\Enums\Order\OrderStatus;
use App\Console\Commands\Recommendation\DispatchPopularRecommendationsBuildCommand;
use App\Console\Commands\Recommendation\DispatchUserRecommendationsBatchBuildCommand;
use App\Console\Commands\Recommendation\DispatchUserRecommendationsBuildCommand;
use App\Console\Commands\Recommendation\PruneBookInteractionEventsCommand;
use App\Enums\Recommendation\BookInteractionType;
use App\Enums\Review\ReviewStatus;
use App\Jobs\Recommendation\BuildPopularRecommendations;
use App\Jobs\Recommendation\BuildUserRecommendations;
use App\Models\Account;
use App\Models\Author;
use App\Models\Book;
use App\Models\BookInteractionEvent;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Review;
use App\Models\Warehouse;
use App\Models\Wishlist;
use App\Services\Recommendation\RecommendationCandidateService;
use App\Services\Recommendation\RecommendationCacheService;
use App\Services\Recommendation\RecommendationRefreshService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function createBookWithStock(int $stock, array $attributes = []): Book
{
    $book = Book::factory()->create($attributes);
    $warehouseId = (int) (Warehouse::query()->value('id') ?? Warehouse::factory()->create()->id);

    Inventory::factory()->create([
        'book_id' => $book->id,
        'warehouse_id' => $warehouseId,
        'quantity' => max($stock, 0),
        'reserved_quantity' => 0,
    ]);

    return $book;
}

function createCompletedOrderItem(Book $book, int $quantity): void
{
    createCompletedOrderItemForAccount($book, $quantity);
}

function createCompletedOrderItemForAccount(Book $book, int $quantity, ?Account $account = null, ?\Illuminate\Support\Carbon $createdAt = null): int
{
    $account ??= Account::factory()->create();
    $createdAt ??= now();

    $shippingMethodId = (int) DB::table('shipping_methods')->insertGetId([
        'name' => 'Nhanh',
        'description' => 'Giao nhanh',
        'is_active' => true,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);

    $orderId = (int) DB::table('orders')->insertGetId([
        'account_id' => $account->id,
        'shipping_method_id' => $shippingMethodId,
        'total_amount' => 100000,
        'shipping_fee' => 0,
        'final_amount' => 100000,
        'shipping_name' => 'Test User',
        'shipping_phone' => '0900000000',
        'shipping_address' => 'HCM',
        'payment_method' => null,
        'payment_status' => null,
        'payment_expires_at' => null,
        'refund_deadline_at' => null,
        'note' => null,
        'current_status' => OrderStatus::COMPLETED->value,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);

    return (int) DB::table('order_items')->insertGetId([
        'order_id' => $orderId,
        'book_id' => $book->id,
        'promotion_item_id' => null,
        'promotion_id' => null,
        'price' => 100000,
        'quantity' => $quantity,
        'total_price' => 100000 * $quantity,
        'discount_amount' => null,
        'is_reviewed' => false,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);
}

function linkBookToCategoryAndAuthor(Book $book, Category $category, Author $author): void
{
    DB::table('book_categories')->insert([
        'book_id' => $book->id,
        'category_id' => $category->id,
    ]);

    DB::table('book_authors')->insert([
        'book_id' => $book->id,
        'author_id' => $author->id,
    ]);
}

test('recommendations endpoint returns popular feed and filters inactive and out of stock books', function (): void {
    $bookInStock = createBookWithStock(5, ['name' => 'Book A']);
    $bookOutOfStock = createBookWithStock(0, ['name' => 'Book B']);
    $bookInactive = createBookWithStock(3, ['is_active' => false, 'name' => 'Book C']);

    app(RecommendationCacheService::class)->putPopular([
        $bookOutOfStock->id,
        $bookInactive->id,
        $bookInStock->id,
    ]);

    $response = $this->getJson('/api/v1/recommendations?limit=10');

    $response->assertOk()
        ->assertJsonPath('meta.feed', 'for_you')
        ->assertJsonPath('meta.strategy', 'popular')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $bookInStock->id)
        ->assertJsonPath('data.0.available_stock', 5);
});

test('recommendations endpoint returns empty data when popular cache missing', function (): void {
    $response = $this->getJson('/api/v1/recommendations');

    $response->assertOk()
        ->assertJsonPath('meta.feed', 'for_you')
        ->assertJsonPath('meta.strategy', 'popular')
        ->assertJsonPath('data', []);
});

test('build popular recommendations job writes cache payload with strategy and candidate ids', function (): void {
    $bookHighSales = createBookWithStock(5);
    $bookNoSales = createBookWithStock(5);
    $bookInactive = createBookWithStock(5, ['is_active' => false]);

    createCompletedOrderItem($bookHighSales, 6);
    createCompletedOrderItem($bookNoSales, 1);
    createCompletedOrderItem($bookInactive, 20);

    $job = app(BuildPopularRecommendations::class);
    $job->handle(
        app(\App\Services\Recommendation\RecommendationCandidateService::class),
        app(RecommendationCacheService::class),
    );

    $payload = app(RecommendationCacheService::class)->getPopular();

    expect($payload)->not->toBeNull()
        ->and($payload['strategy'])->toBe('popular')
        ->and($payload['book_ids'])->toContain($bookHighSales->id)
        ->and($payload['book_ids'])->toContain($bookNoSales->id)
        ->and($payload['book_ids'])->not->toContain($bookInactive->id);
});

test('recommendations endpoint degrades gracefully when cache read fails', function (): void {
    $this->mock(RecommendationCacheService::class, function ($mock): void {
        $mock->shouldReceive('getPopular')
        ->once()
        ->andThrow(new RuntimeException('Redis unavailable'));
    });

    $response = $this->getJson('/api/v1/recommendations');

    $response->assertOk()
        ->assertJsonPath('meta.feed', 'for_you')
        ->assertJsonPath('meta.strategy', 'popular')
        ->assertJsonPath('data', []);
});

test('popular recommendation job is unique to avoid overlapping builds', function (): void {
    $job = new BuildPopularRecommendations();

    expect($job)->toBeInstanceOf(ShouldBeUnique::class)
        ->and($job->uniqueId())->toBe('recommendation:popular')
        ->and($job->uniqueFor)->toBe(1800);
});

test('popular recommendation dispatch command enqueues build job', function (): void {
    Bus::fake();

    $this->artisan(DispatchPopularRecommendationsBuildCommand::class)
        ->assertSuccessful();

    Bus::assertDispatched(BuildPopularRecommendations::class);
});

test('schedule registers popular recommendation build every six hours', function (): void {
    $schedule = app(Schedule::class);
    $event = collect($schedule->events())
        ->first(fn ($item): bool => str_contains((string) $item->command, 'recommendations:build-popular'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 */6 * * *');
});

test('schedule registers user batch build hourly and interaction prune daily', function (): void {
    $schedule = app(Schedule::class);
    $buildUsers = collect($schedule->events())
        ->first(fn ($item): bool => str_contains((string) $item->command, 'recommendations:build-users'));
    $pruneInteractions = collect($schedule->events())
        ->first(fn ($item): bool => str_contains((string) $item->command, 'recommendations:prune-interactions'));

    expect($buildUsers)->not->toBeNull()
        ->and($buildUsers->expression)->toBe('0 * * * *');
    expect($pruneInteractions)->not->toBeNull()
        ->and($pruneInteractions->expression)->toBe('0 0 * * *');
});

test('member uses content based strategy when user cache exists', function (): void {
    $account = Account::factory()->create();
    $personalizedBook = createBookWithStock(5);
    $popularBook = createBookWithStock(5);

    app(RecommendationCacheService::class)->putUser($account->id, [$personalizedBook->id], 'content_based');
    app(RecommendationCacheService::class)->putPopular([$popularBook->id]);

    $response = $this->actingAs($account, 'web')->getJson('/api/v1/recommendations');

    $response->assertOk()
        ->assertJsonPath('meta.strategy', 'content_based')
        ->assertJsonPath('data.0.id', $personalizedBook->id);
});

test('member falls back to popular strategy when user cache missing', function (): void {
    $account = Account::factory()->create();
    $popularBook = createBookWithStock(5);

    app(RecommendationCacheService::class)->putPopular([$popularBook->id]);

    $response = $this->actingAs($account, 'web')->getJson('/api/v1/recommendations');

    $response->assertOk()
        ->assertJsonPath('meta.strategy', 'popular')
        ->assertJsonPath('data.0.id', $popularBook->id);
});

test('member personalized feed is topped up from popular when personalized books are ineligible', function (): void {
    $account = Account::factory()->create();
    $personalizedOutOfStock = createBookWithStock(0);
    $personalizedInactive = createBookWithStock(5, ['is_active' => false]);
    $popularBook = createBookWithStock(5);

    app(RecommendationCacheService::class)->putUser($account->id, [
        $personalizedOutOfStock->id,
        $personalizedInactive->id,
    ], 'content_based');
    app(RecommendationCacheService::class)->putPopular([$popularBook->id]);

    $response = $this->actingAs($account, 'web')->getJson('/api/v1/recommendations?limit=3');

    $response->assertOk()
        ->assertJsonPath('meta.strategy', 'content_based')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $popularBook->id);
});

test('build user recommendation job creates personalized cache for qualified member', function (): void {
    $account = Account::factory()->create();
    $category = Category::factory()->create();
    $author = Author::factory()->create();

    $seedBooks = collect(range(1, 5))->map(fn () => createBookWithStock(5));
    $candidateBook = createBookWithStock(5);

    foreach ($seedBooks as $book) {
        linkBookToCategoryAndAuthor($book, $category, $author);
    }
    linkBookToCategoryAndAuthor($candidateBook, $category, $author);

    Wishlist::query()->create([
        'account_id' => $account->id,
        'book_id' => $seedBooks[0]->id,
    ]);

    foreach ($seedBooks->slice(1) as $book) {
        BookInteractionEvent::query()->create([
            'account_id' => $account->id,
            'book_id' => $book->id,
            'event_type' => BookInteractionType::View,
            'created_at' => now(),
        ]);
    }

    Review::query()->create([
        'account_id' => $account->id,
        'book_id' => $seedBooks[0]->id,
        'order_item_id' => createCompletedOrderItemForAccount($seedBooks[0], 1, $account),
        'rating' => 5.0,
        'status' => ReviewStatus::APPROVED,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $job = new BuildUserRecommendations($account->id);
    $job->handle(
        app(RecommendationCandidateService::class),
        app(RecommendationCacheService::class),
    );

    $payload = app(RecommendationCacheService::class)->getUser($account->id);

    expect($payload)->not->toBeNull()
        ->and($payload['strategy'])->toBe('content_based')
        ->and($payload['book_ids'])->toContain($candidateBook->id);
});

test('build user recommendation job forgets cache for insufficient signals', function (): void {
    $account = Account::factory()->create();
    $book = createBookWithStock(5);
    app(RecommendationCacheService::class)->putUser($account->id, [$book->id], 'content_based');

    $job = new BuildUserRecommendations($account->id);
    $job->handle(
        app(RecommendationCandidateService::class),
        app(RecommendationCacheService::class),
    );

    expect(app(RecommendationCacheService::class)->getUser($account->id))->toBeNull();
});

test('user candidate builder excludes recently purchased books', function (): void {
    $account = Account::factory()->create();
    $category = Category::factory()->create();
    $author = Author::factory()->create();

    $seedBooks = collect(range(1, 5))->map(fn () => createBookWithStock(5));
    $recentPurchasedCandidate = createBookWithStock(5);

    foreach ($seedBooks as $book) {
        linkBookToCategoryAndAuthor($book, $category, $author);
    }
    linkBookToCategoryAndAuthor($recentPurchasedCandidate, $category, $author);

    Wishlist::query()->create([
        'account_id' => $account->id,
        'book_id' => $seedBooks[0]->id,
    ]);

    foreach ($seedBooks->slice(1) as $book) {
        BookInteractionEvent::query()->create([
            'account_id' => $account->id,
            'book_id' => $book->id,
            'event_type' => BookInteractionType::View,
            'created_at' => now(),
        ]);
    }

    createCompletedOrderItemForAccount($recentPurchasedCandidate, 1, $account, now());

    $candidateIds = app(RecommendationCandidateService::class)->buildUserCandidateBookIds($account->id);

    expect($candidateIds)->not->toContain($recentPurchasedCandidate->id);
});

test('user recommendation dispatch command enqueues user build job', function (): void {
    Bus::fake();
    $account = Account::factory()->create();

    $this->artisan(DispatchUserRecommendationsBuildCommand::class, ['account_id' => $account->id])
        ->assertSuccessful();

    Bus::assertDispatched(BuildUserRecommendations::class, fn (BuildUserRecommendations $job): bool => $job->accountId === $account->id);
});

test('user recommendation batch build command dispatches jobs for recent signal accounts', function (): void {
    Bus::fake();
    $recentAccount = Account::factory()->create();
    $book = createBookWithStock(5);

    BookInteractionEvent::query()->create([
        'account_id' => $recentAccount->id,
        'book_id' => $book->id,
        'event_type' => BookInteractionType::View,
        'created_at' => now(),
    ]);

    $this->artisan(DispatchUserRecommendationsBatchBuildCommand::class, ['--recent-days' => 7])
        ->assertSuccessful();

    Bus::assertDispatched(BuildUserRecommendations::class, fn (BuildUserRecommendations $job): bool => $job->accountId === $recentAccount->id);
});

test('prune interaction events command deletes events older than retention', function (): void {
    $account = Account::factory()->create();
    $book = createBookWithStock(5);

    BookInteractionEvent::query()->create([
        'account_id' => $account->id,
        'book_id' => $book->id,
        'event_type' => BookInteractionType::View,
        'created_at' => now()->subDays(200),
    ]);
    BookInteractionEvent::query()->create([
        'account_id' => $account->id,
        'book_id' => $book->id,
        'event_type' => BookInteractionType::CartAdd,
        'created_at' => now()->subDays(2),
    ]);

    $this->artisan(PruneBookInteractionEventsCommand::class)->assertSuccessful();

    expect(BookInteractionEvent::query()->where('event_type', BookInteractionType::View)->count())->toBe(0)
        ->and(BookInteractionEvent::query()->where('event_type', BookInteractionType::CartAdd)->count())->toBe(1);
});

test('recommendation refresh service debounces repeated dispatches for same account', function (): void {
    Bus::fake();
    $account = Account::factory()->create();
    app(RecommendationCacheService::class)->putUser($account->id, [123], 'content_based');

    $service = app(RecommendationRefreshService::class);
    $service->refreshUserRecommendations($account->id, 'test_1');
    $service->refreshUserRecommendations($account->id, 'test_2');

    expect(app(RecommendationCacheService::class)->getUser($account->id))->toBeNull();
    Bus::assertDispatchedTimes(BuildUserRecommendations::class, 1);
});

test('review approved signal triggers recommendation refresh dispatch', function (): void {
    Bus::fake();
    $account = Account::factory()->create();
    $book = createBookWithStock(5);

    $review = Review::query()->create([
        'account_id' => $account->id,
        'book_id' => $book->id,
        'order_item_id' => createCompletedOrderItemForAccount($book, 1, $account),
        'rating' => 5.0,
        'status' => ReviewStatus::PENDING,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $review->update([
        'status' => ReviewStatus::APPROVED,
    ]);

    Bus::assertDispatched(BuildUserRecommendations::class, fn (BuildUserRecommendations $job): bool => $job->accountId === $account->id);
});
