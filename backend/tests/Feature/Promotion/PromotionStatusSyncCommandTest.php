<?php

use App\Enums\Promotion\PromotionStatus;
use App\Models\Promotion;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('promotion status sync activates and expires promotions by time window', function (): void {
    $scheduled = Promotion::query()->create([
        'name' => 'Starts now',
        'type' => 'flash_sale',
        'start_at' => now()->subMinute(),
        'end_at' => now()->addHour(),
        'status' => PromotionStatus::SCHEDULED,
    ]);

    $active = Promotion::query()->create([
        'name' => 'Ends now',
        'type' => 'flash_sale',
        'start_at' => now()->subHours(2),
        'end_at' => now()->subMinute(),
        'status' => PromotionStatus::ACTIVE,
    ]);

    $cancelled = Promotion::query()->create([
        'name' => 'Cancelled stays cancelled',
        'type' => 'flash_sale',
        'start_at' => now()->subHours(2),
        'end_at' => now()->subMinute(),
        'status' => PromotionStatus::CANCELLED,
    ]);

    $this->artisan('promotions:sync-status')
        ->assertSuccessful();

    expect($scheduled->fresh()->status)->toBe(PromotionStatus::ACTIVE)
        ->and($active->fresh()->status)->toBe(PromotionStatus::EXPIRED)
        ->and($cancelled->fresh()->status)->toBe(PromotionStatus::CANCELLED);
});
