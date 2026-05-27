<?php

namespace App\Services\Promotion;

use App\Enums\Promotion\PromotionAllocationStatus;
use App\Enums\Promotion\PromotionStatus;
use App\Enums\Promotion\PromotionType;
use App\Models\Account;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Promotion;
use App\Models\PromotionAllocation;
use App\Models\PromotionItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class PromotionAllocationService
{
    public function __construct(
        private FlashSaleResolver $flashSaleResolver,
    ) {}

    public function reserve(Account $account, PromotionItem $promotionItem, OrderItem $orderItem): PromotionAllocation
    {
        $quantity = (int) $orderItem->quantity;

        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'promotion' => ['Số lượng áp dụng khuyến mãi không hợp lệ.'],
            ]);
        }

        try {
            /** @var PromotionItem $lockedItem */
            $lockedItem = PromotionItem::query()
                ->with('promotion')
                ->whereKey($promotionItem->id)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var Promotion $lockedPromotion */
            $lockedPromotion = Promotion::query()
                ->whereKey($lockedItem->promotion_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertFlashSaleEligible($lockedPromotion);

            if (! $this->flashSaleResolver->isItemApplicableForQuantity($lockedItem, $quantity, (int) $account->id)) {
                throw ValidationException::withMessages([
                    'promotion' => ['Khuyến mãi đã hết suất hoặc vượt giới hạn mua.'],
                ]);
            }

            $affected = PromotionItem::query()
                ->whereKey($lockedItem->id)
                ->where(function ($query) use ($quantity): void {
                    $query
                        ->whereNull('stock_limit')
                        ->orWhereRaw('sold_quantity + ? <= stock_limit', [$quantity]);
                })
                ->update([
                    'sold_quantity' => DB::raw('sold_quantity + '.$quantity),
                ]);

            if ($affected !== 1) {
                throw ValidationException::withMessages([
                    'promotion' => ['Khuyến mãi đã hết suất.'],
                ]);
            }

            return PromotionAllocation::query()->create([
                'promotion_item_id' => $lockedItem->id,
                'account_id' => $account->id,
                'order_id' => $orderItem->order_id,
                'order_item_id' => $orderItem->id,
                'quantity' => $quantity,
                'status' => PromotionAllocationStatus::RESERVED,
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Reserve promotion allocation failed', [
                'promotion_item_id' => $promotionItem->id,
                'order_item_id' => $orderItem->id,
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function confirmForOrder(Order $order): void
    {
        PromotionAllocation::query()
            ->where('order_id', $order->id)
            ->where('status', PromotionAllocationStatus::RESERVED->value)
            ->update([
                'status' => PromotionAllocationStatus::CONFIRMED->value,
            ]);
    }

    public function releaseForOrder(Order $order): void
    {
        try {
            $allocations = PromotionAllocation::query()
                ->where('order_id', $order->id)
                ->whereIn('status', [
                    PromotionAllocationStatus::RESERVED->value,
                    PromotionAllocationStatus::CONFIRMED->value,
                ])
                ->lockForUpdate()
                ->get();

            foreach ($allocations as $allocation) {
                $quantity = (int) $allocation->quantity;

                /** @var PromotionItem|null $promotionItem */
                $promotionItem = PromotionItem::query()
                    ->whereKey($allocation->promotion_item_id)
                    ->lockForUpdate()
                    ->first();

                if ($promotionItem !== null && $quantity > 0) {
                    $releaseQuantity = min($quantity, (int) $promotionItem->sold_quantity);

                    if ($releaseQuantity > 0) {
                        $promotionItem->decrement('sold_quantity', $releaseQuantity);
                    }
                }

                $allocation->update([
                    'status' => PromotionAllocationStatus::RELEASED,
                ]);
            }
        } catch (Throwable $e) {
            Log::error('Release promotion allocations for order failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function assertFlashSaleEligible(Promotion $promotion): void
    {
        $now = now();

        if ($promotion->type !== PromotionType::FLASH_SALE
            || ! in_array($promotion->status, [
                PromotionStatus::SCHEDULED,
                PromotionStatus::ACTIVE,
            ], true)
            || $promotion->start_at > $now
            || $promotion->end_at <= $now) {
            throw ValidationException::withMessages([
                'promotion' => ['Flash Sale không còn hiệu lực.'],
            ]);
        }
    }
}
