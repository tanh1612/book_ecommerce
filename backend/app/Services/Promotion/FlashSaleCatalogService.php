<?php

namespace App\Services\Promotion;

use App\Models\Promotion;
use App\Models\PromotionItem;
use Illuminate\Support\Collection;

class FlashSaleCatalogService
{
    public function __construct(
        private FlashSaleResolver $flashSaleResolver,
    ) {}

    /**
     * @return array{campaign: Promotion, items: Collection<int, PromotionItem>}|null
     */
    public function activeCampaignWithItems(): ?array
    {
        $campaign = $this->flashSaleResolver->activeCampaign();

        if ($campaign === null) {
            return null;
        }

        $items = PromotionItem::query()
            ->with([
                'book.images',
                'book.inventories',
            ])
            ->where('promotion_id', $campaign->id)
            ->orderByDesc('discount_value')
            ->orderBy('id')
            ->get()
            ->filter(fn (PromotionItem $item): bool => $this->flashSaleResolver->isItemSellableForDisplay($item));

        return [
            'campaign' => $campaign,
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function flashSalePayloadForBook(int $bookId, int $quantity = 1, ?int $accountId = null): ?array
    {
        $items = $this->flashSaleResolver->activeItemsForBooks(
            [$bookId],
            $accountId,
            [$bookId => $quantity],
        );

        $item = $items->get($bookId);

        if ($item === null) {
            return null;
        }

        return $this->formatFlashSalePayload($item);
    }

    /**
     * @return array<string, mixed>
     */
    public function formatFlashSalePayload(PromotionItem $item): array
    {
        $item->loadMissing('promotion');
        $promotion = $item->promotion;

        return [
            'promotion_item_id' => $item->id,
            'discount_percent' => (float) $item->discount_value,
            'end_at' => $promotion?->end_at?->toIso8601String(),
            'remaining_stock' => $this->flashSaleResolver->displayRemainingStock($item),
        ];
    }
}
