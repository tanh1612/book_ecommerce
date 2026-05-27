<?php

namespace App\Services\Promotion;

use App\Models\Promotion;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class FlashSaleCampaignValidator
{
    public function __construct(
        private FlashSaleOverlapValidator $overlapValidator,
        private FlashSaleScheduleMutex $scheduleMutex,
    ) {}

    /**
     * @param  array<int, int>|null  $bookIds
     */
    public function assertScheduledCampaignRules(
        Carbon $startAt,
        Carbon $endAt,
        ?int $promotionId = null,
        ?array $bookIds = null,
        bool $nested = false,
    ): void {
        $this->scheduleMutex->runExclusive(
            function () use ($startAt, $endAt, $promotionId, $bookIds): void {
                $this->overlapValidator->assertNoOverlappingFlashSaleCampaign($startAt, $endAt, $promotionId);

                if ($bookIds === null) {
                    return;
                }

                foreach (array_values(array_unique(array_map('intval', $bookIds))) as $bookId) {
                    if ($bookId <= 0) {
                        continue;
                    }

                    $this->overlapValidator->assertBookAvailableForFlashSaleWindow(
                        $bookId,
                        $startAt,
                        $endAt,
                        $promotionId,
                    );
                }
            },
            nested: $nested,
        );
    }

    public function assertPromotionItemsAgainstWindow(Promotion $promotion): void
    {
        $bookIds = $promotion->items()
            ->pluck('book_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $this->assertScheduledCampaignRules(
            $promotion->start_at,
            $promotion->end_at,
            (int) $promotion->id,
            $bookIds,
            nested: true,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function assertEditFormData(Promotion $promotion, array $data): void
    {
        $startAt = isset($data['start_at'])
            ? Carbon::parse($data['start_at'])
            : $promotion->start_at;

        $endAt = isset($data['end_at'])
            ? Carbon::parse($data['end_at'])
            : $promotion->end_at;

        $bookIds = $promotion->items()
            ->pluck('book_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        try {
            $this->assertScheduledCampaignRules($startAt, $endAt, (int) $promotion->id, $bookIds, nested: true);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();

            throw ValidationException::withMessages([
                'start_at' => [$message ?? 'Thời gian chiến dịch Flash Sale không hợp lệ.'],
            ]);
        }
    }
}
