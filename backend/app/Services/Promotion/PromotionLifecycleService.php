<?php

namespace App\Services\Promotion;

use App\Enums\Promotion\PromotionStatus;
use App\Models\Promotion;
use App\Models\PromotionItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class PromotionLifecycleService
{
    public function canEdit(Promotion $promotion): bool
    {
        return $promotion->status === PromotionStatus::SCHEDULED
            && $promotion->start_at->isFuture();
    }

    public function canCancel(Promotion $promotion): bool
    {
        return $this->canEdit($promotion);
    }

    public function canDelete(Promotion $promotion): bool
    {
        return $this->canEdit($promotion) && ! $this->hasBeenUsed($promotion);
    }

    public function cancel(Promotion $promotion): void
    {
        $affected = Promotion::query()
            ->whereKey($promotion->id)
            ->where('status', PromotionStatus::SCHEDULED->value)
            ->where('start_at', '>', now())
            ->update(['status' => PromotionStatus::CANCELLED->value]);

        if ($affected !== 1) {
            throw ValidationException::withMessages([
                'status' => ['Chỉ chiến dịch sắp diễn ra mới được hủy.'],
            ]);
        }
    }

    public function deleteScheduledPromotion(Promotion $promotion): void
    {
        $this->deleteScheduledPromotions([$promotion]);
    }

    /**
     * @param  iterable<int, Promotion>  $promotions
     */
    public function deleteScheduledPromotions(iterable $promotions): void
    {
        DB::transaction(function () use ($promotions): void {
            $idList = [];

            foreach ($promotions as $promotion) {
                if ($promotion instanceof Promotion) {
                    $idList[(int) $promotion->id] = true;
                }
            }

            if ($idList === []) {
                return;
            }

            $ids = array_keys($idList);
            sort($ids);

            $locked = Promotion::query()
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($locked->count() !== count($ids)) {
                throw ValidationException::withMessages([
                    'promotion' => ['Một hoặc nhiều chiến dịch không tồn tại.'],
                ]);
            }

            foreach ($locked as $lockedPromotion) {
            if (! $this->canEdit($lockedPromotion)) {
                    throw ValidationException::withMessages([
                        'status' => ['Chỉ chiến dịch sắp diễn ra mới được xóa.'],
                    ]);
                }

                if ($this->hasBeenUsed($lockedPromotion)) {
                    throw ValidationException::withMessages([
                        'promotion' => ['Không thể xóa chiến dịch đã được áp dụng trên đơn hàng.'],
                    ]);
                }
            }

            Promotion::query()->whereIn('id', $ids)->delete();
        });
    }

    /**
     * @template TReturn
     *
     * @param  callable(Promotion): TReturn  $callback
     * @return TReturn
     */
    public function runWhileScheduled(Promotion $promotion, callable $callback): mixed
    {
        return DB::transaction(function () use ($promotion, $callback): mixed {
            /** @var Promotion $locked */
            $locked = Promotion::query()
                ->whereKey($promotion->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $this->canEdit($locked)) {
                throw ValidationException::withMessages([
                    'status' => ['Chỉ chiến dịch sắp diễn ra mới được thao tác.'],
                ]);
            }

            return $callback($locked);
        });
    }

    /**
     * @template TReturn
     *
     * @param  callable(Promotion): TReturn  $callback
     * @return TReturn
     */
    public function updateScheduledPromotion(Promotion $promotion, callable $callback): mixed
    {
        return $this->runWhileScheduled($promotion, $callback);
    }

    public function hasPromotionItemBeenUsed(PromotionItem|int $item): bool
    {
        $itemId = $item instanceof PromotionItem ? (int) $item->id : $item;

        if (DB::table('order_items')->where('promotion_item_id', $itemId)->exists()) {
            return true;
        }

        return DB::table('promotion_allocations')->where('promotion_item_id', $itemId)->exists();
    }

    public function assertPromotionItemDeletable(PromotionItem $item): void
    {
        if ($this->hasPromotionItemBeenUsed($item)) {
            throw ValidationException::withMessages([
                'promotion_item' => ['Không thể xóa sản phẩm đã được áp dụng trên đơn hàng hoặc đã có phân bổ khuyến mãi.'],
            ]);
        }
    }

    public function deleteScheduledPromotionItem(Promotion $promotion, PromotionItem $item): void
    {
        $this->runWhileScheduled($promotion, function (Promotion $locked) use ($item): void {
            $this->assertPromotionItemBelongsToPromotion($item, $locked);

            /** @var PromotionItem $lockedItem */
            $lockedItem = PromotionItem::query()
                ->whereKey($item->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertPromotionItemDeletable($lockedItem);
            $lockedItem->delete();
        });
    }

    /**
     * @param  iterable<int, PromotionItem>  $items
     */
    public function deleteScheduledPromotionItems(Promotion $promotion, iterable $items): void
    {
        $this->runWhileScheduled($promotion, function (Promotion $locked) use ($items): void {
            $itemIds = [];

            foreach ($items as $item) {
                if (! $item instanceof PromotionItem) {
                    continue;
                }

                $this->assertPromotionItemBelongsToPromotion($item, $locked);
                $itemIds[(int) $item->id] = true;
            }

            if ($itemIds === []) {
                return;
            }

            $ids = array_keys($itemIds);

            $lockedItems = PromotionItem::query()
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get();

            foreach ($lockedItems as $lockedItem) {
                $this->assertPromotionItemDeletable($lockedItem);
            }

            PromotionItem::query()->whereIn('id', $ids)->delete();
        });
    }

    public function hasBeenUsed(Promotion $promotion): bool
    {
        $promotionId = (int) $promotion->id;

        if (DB::table('order_items')->where('promotion_id', $promotionId)->exists()) {
            return true;
        }

        $itemIds = DB::table('promotion_items')
            ->where('promotion_id', $promotionId)
            ->pluck('id');

        if ($itemIds->isEmpty()) {
            return false;
        }

        if (DB::table('order_items')->whereIn('promotion_item_id', $itemIds)->exists()) {
            return true;
        }

        return DB::table('promotion_allocations')
            ->whereIn('promotion_item_id', $itemIds)
            ->exists();
    }

    private function assertPromotionItemBelongsToPromotion(PromotionItem $item, Promotion $promotion): void
    {
        if ((int) $item->promotion_id !== (int) $promotion->id) {
            throw ValidationException::withMessages([
                'status' => ['Sản phẩm không thuộc chiến dịch này.'],
            ]);
        }
    }

    public function assertDeletable(Promotion $promotion): void
    {
        $fresh = Promotion::query()->whereKey($promotion->id)->first();

        if ($fresh === null || ! $this->canEdit($fresh)) {
            throw ValidationException::withMessages([
                'status' => ['Chỉ chiến dịch sắp diễn ra mới được xóa.'],
            ]);
        }

        if ($this->hasBeenUsed($fresh)) {
            throw ValidationException::withMessages([
                'promotion' => ['Không thể xóa chiến dịch đã được áp dụng trên đơn hàng.'],
            ]);
        }
    }

    /**
     * @return array{activated: int, expired: int}
     */
    public function syncStatuses(): array
    {
        try {
            $now = now();

            $activated = Promotion::query()
                ->where('status', PromotionStatus::SCHEDULED->value)
                ->where('start_at', '<=', $now)
                ->where('end_at', '>', $now)
                ->update(['status' => PromotionStatus::ACTIVE->value]);

            $expired = Promotion::query()
                ->whereIn('status', [
                    PromotionStatus::SCHEDULED->value,
                    PromotionStatus::ACTIVE->value,
                ])
                ->where('end_at', '<=', $now)
                ->update(['status' => PromotionStatus::EXPIRED->value]);

            return [
                'activated' => (int) $activated,
                'expired' => (int) $expired,
            ];
        } catch (Throwable $e) {
            Log::error('Promotion status sync failed', [
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            throw $e;
        }
    }
}
