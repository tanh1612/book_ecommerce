<?php

namespace App\Services\Promotion;

use App\Enums\Promotion\PromotionAllocationStatus;
use App\Models\Account;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PromotionAllocation;
use App\Models\PromotionItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class PromotionAllocationService
{
    public function reserve(Account $account, PromotionItem $promotionItem, OrderItem $orderItem): PromotionAllocation
    {
        $quantity = (int) $orderItem->quantity;

        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'promotion' => ['Số lượng áp dụng khuyến mãi không hợp lệ.'],
            ]);
        }

        try {
            $affected = PromotionItem::query()
                ->whereKey($promotionItem->id)
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

            /** @var PromotionItem $locked */
            $locked = PromotionItem::query()
                ->whereKey($promotionItem->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->max_quantity_per_user !== null) {
                $usedByCustomer = (int) PromotionAllocation::query()
                    ->where('promotion_item_id', $locked->id)
                    ->where('account_id', $account->id)
                    ->whereIn('status', [
                        PromotionAllocationStatus::RESERVED->value,
                        PromotionAllocationStatus::CONFIRMED->value,
                    ])
                    ->lockForUpdate()
                    ->sum('quantity');

                if ($usedByCustomer + $quantity > (int) $locked->max_quantity_per_user) {
                    throw ValidationException::withMessages([
                        'promotion' => ['Bạn đã vượt quá số lượng mua tối đa của khuyến mãi này.'],
                    ]);
                }
            }

            return PromotionAllocation::query()->create([
                'promotion_item_id' => $locked->id,
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
}
