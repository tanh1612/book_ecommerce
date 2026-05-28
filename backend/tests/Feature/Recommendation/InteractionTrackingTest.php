<?php

use App\Enums\Recommendation\BookInteractionType;
use App\Jobs\Recommendation\BuildUserRecommendations;
use App\Models\Account;
use App\Models\Book;
use App\Models\BookInteractionEvent;
use App\Services\Recommendation\InteractionTrackingService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutMiddleware(VerifyCsrfToken::class);
});

test('guest cannot track book view interaction', function (): void {
    $book = Book::factory()->create();

    $this->postJson("/api/v1/recommendations/interactions/books/{$book->id}/view", [
        'source' => 'book_detail',
    ])->assertUnauthorized();
});

test('member can track first book view', function (): void {
    $account = Account::factory()->create();
    $book = Book::factory()->create();
    Bus::fake();

    $this->actingAs($account, 'web');

    $this->postJson("/api/v1/recommendations/interactions/books/{$book->id}/view", [
        'source' => 'book_detail',
    ])->assertNoContent();

    $this->assertDatabaseHas('book_interaction_events', [
        'account_id' => $account->id,
        'book_id' => $book->id,
        'event_type' => BookInteractionType::View->value,
        'source' => 'book_detail',
    ]);

    Bus::assertDispatched(BuildUserRecommendations::class, fn (BuildUserRecommendations $job): bool => $job->accountId === $account->id);
});

test('member view deduplicates same book within thirty minutes', function (): void {
    Carbon::setTestNow(now());

    $account = Account::factory()->create();
    $book = Book::factory()->create();
    $this->actingAs($account, 'web');

    $this->postJson("/api/v1/recommendations/interactions/books/{$book->id}/view")->assertNoContent();
    $this->postJson("/api/v1/recommendations/interactions/books/{$book->id}/view")->assertNoContent();

    expect(BookInteractionEvent::query()
        ->where('account_id', $account->id)
        ->where('book_id', $book->id)
        ->where('event_type', BookInteractionType::View)
        ->count())->toBe(1);

    Carbon::setTestNow();
});

test('member view after dedup window creates new event', function (): void {
    Carbon::setTestNow(now());

    $account = Account::factory()->create();
    $book = Book::factory()->create();
    $this->actingAs($account, 'web');

    $this->postJson("/api/v1/recommendations/interactions/books/{$book->id}/view")->assertNoContent();

    Carbon::setTestNow(now()->addMinutes(31));
    $this->postJson("/api/v1/recommendations/interactions/books/{$book->id}/view")->assertNoContent();

    expect(BookInteractionEvent::query()
        ->where('account_id', $account->id)
        ->where('book_id', $book->id)
        ->where('event_type', BookInteractionType::View)
        ->count())->toBe(2);

    Carbon::setTestNow();
});

test('member view for different books creates separate events', function (): void {
    $account = Account::factory()->create();
    $bookA = Book::factory()->create();
    $bookB = Book::factory()->create();
    $this->actingAs($account, 'web');

    $this->postJson("/api/v1/recommendations/interactions/books/{$bookA->id}/view")->assertNoContent();
    $this->postJson("/api/v1/recommendations/interactions/books/{$bookB->id}/view")->assertNoContent();

    expect(BookInteractionEvent::query()
        ->where('account_id', $account->id)
        ->where('event_type', BookInteractionType::View)
        ->count())->toBe(2);
});

test('track view returns false when cache lock infrastructure fails', function (): void {
    $account = Account::factory()->create();
    $book = Book::factory()->create();
    $lockKey = sprintf('reco:track:view:%d:%d', $account->id, $book->id);

    Cache::partialMock()
        ->shouldReceive('lock')
        ->once()
        ->with($lockKey, 10)
        ->andThrow(new RuntimeException('Redis connection refused'));

    $service = app(InteractionTrackingService::class);

    expect($service->trackView($account, $book, 'book_detail'))->toBeFalse();
    expect(BookInteractionEvent::query()->count())->toBe(0);
});

test('track view endpoint returns no content when tracking is skipped', function (): void {
    $account = Account::factory()->create();
    $book = Book::factory()->create();

    $this->mock(InteractionTrackingService::class, function ($mock) use ($account, $book): void {
        $mock->shouldReceive('trackView')
            ->once()
            ->with(
                Mockery::on(fn (Account $user): bool => $user->is($account)),
                Mockery::on(fn (Book $trackedBook): bool => $trackedBook->is($book)),
                'book_detail',
            )
            ->andReturn(false);
    });

    $this->actingAs($account, 'web');

    $this->postJson("/api/v1/recommendations/interactions/books/{$book->id}/view", [
        'source' => 'book_detail',
    ])->assertNoContent();
});

test('track view returns false when lock cannot be acquired', function (): void {
    $account = Account::factory()->create();
    $book = Book::factory()->create();
    $lockKey = sprintf('reco:track:view:%d:%d', $account->id, $book->id);

    Cache::partialMock()
        ->shouldReceive('lock')
        ->once()
        ->with($lockKey, 10)
        ->andReturn($lock = Mockery::mock());

    $lock->shouldReceive('block')
        ->once()
        ->with(10, Mockery::type('callable'))
        ->andThrow(new LockTimeoutException);

    $service = app(InteractionTrackingService::class);

    expect($service->trackView($account, $book, 'book_detail'))->toBeFalse();

    expect(BookInteractionEvent::query()->count())->toBe(0);
});

test('track book view validates source value', function (): void {
    $account = Account::factory()->create();
    $book = Book::factory()->create();
    $this->actingAs($account, 'web');

    $this->postJson("/api/v1/recommendations/interactions/books/{$book->id}/view", [
        'source' => 'unknown_source',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['source']);
});
