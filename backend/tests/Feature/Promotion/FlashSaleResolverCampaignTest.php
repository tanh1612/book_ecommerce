<?php

use App\Enums\Promotion\PromotionStatus;
use App\Models\Promotion;
use App\Services\Promotion\FlashSaleResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

test('activeCampaign logs critical when multiple flash sales overlap in time window', function (): void {
    Log::shouldReceive('critical')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            return str_contains($message, 'Multiple active flash sale campaigns')
                && count($context['campaign_ids'] ?? []) === 2;
        });

    Promotion::query()->create([
        'name' => 'Campaign A',
        'type' => 'flash_sale',
        'start_at' => now()->subHour(),
        'end_at' => now()->addHour(),
        'status' => PromotionStatus::ACTIVE,
    ]);

    Promotion::query()->create([
        'name' => 'Campaign B',
        'type' => 'flash_sale',
        'start_at' => now()->subMinutes(30),
        'end_at' => now()->addHours(2),
        'status' => PromotionStatus::ACTIVE,
    ]);

    $campaign = app(FlashSaleResolver::class)->activeCampaign();

    expect($campaign)->not->toBeNull();
});
