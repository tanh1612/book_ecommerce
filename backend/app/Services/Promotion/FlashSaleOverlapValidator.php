<?php

namespace App\Services\Promotion;

use App\Enums\Promotion\PromotionStatus;
use App\Enums\Promotion\PromotionType;
use App\Models\Promotion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FlashSaleOverlapValidator
{
    public function assertNoOverlappingFlashSaleCampaign(
        Carbon $startAt,
        Carbon $endAt,
        ?int $ignorePromotionId = null,
    ): void {
        $query = Promotion::query()
            ->where('type', PromotionType::FLASH_SALE->value)
            ->whereIn('status', [
                PromotionStatus::SCHEDULED->value,
                PromotionStatus::ACTIVE->value,
            ])
            ->where('start_at', '<', $endAt)
            ->where('end_at', '>', $startAt);

        if ($ignorePromotionId !== null) {
            $query->whereKeyNot($ignorePromotionId);
        }

        if ($query->lockForUpdate()->exists()) {
            throw ValidationException::withMessages([
                'start_at' => ['Đã có chiến dịch Flash Sale khác trong khoảng thời gian này.'],
            ]);
        }
    }

    public function assertBookAvailableForFlashSaleWindow(
        int $bookId,
        Carbon $startAt,
        Carbon $endAt,
        ?int $ignorePromotionId = null,
    ): void {
        $query = DB::table('promotion_items as pi')
            ->join('promotions as p', 'p.id', '=', 'pi.promotion_id')
            ->where('pi.book_id', $bookId)
            ->where('p.type', PromotionType::FLASH_SALE->value)
            ->whereIn('p.status', [
                PromotionStatus::SCHEDULED->value,
                PromotionStatus::ACTIVE->value,
            ])
            ->where('p.start_at', '<', $endAt)
            ->where('p.end_at', '>', $startAt);

        if ($ignorePromotionId !== null) {
            $query->where('p.id', '!=', $ignorePromotionId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'book_id' => ['Sách này đã nằm trong một Flash Sale có thời gian giao với chiến dịch hiện tại.'],
            ]);
        }
    }
}
