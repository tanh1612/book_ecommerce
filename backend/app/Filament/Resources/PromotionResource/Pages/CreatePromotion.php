<?php

namespace App\Filament\Resources\PromotionResource\Pages;

use App\Enums\Promotion\PromotionStatus;
use App\Enums\Promotion\PromotionType;
use App\Filament\Resources\PromotionResource;
use App\Models\Promotion;
use App\Services\Promotion\FlashSaleCampaignValidator;
use App\Services\Promotion\FlashSaleScheduleMutex;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreatePromotion extends CreateRecord
{
    protected static string $resource = PromotionResource::class;

    protected ?bool $hasDatabaseTransactions = false;

    /**
     * @var list<array{book_id: int, discount_value: int, stock_limit: ?int, max_quantity_per_user: ?int}>
     */
    protected array $pendingItems = [];

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pendingItems = $this->normalizePendingItems(is_array($data['items'] ?? null) ? $data['items'] : []);

        unset($data['items'], $data['status'], $data['type']);

        $data['status'] = PromotionStatus::SCHEDULED->value;
        $data['type'] = PromotionType::FLASH_SALE->value;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        if (! isset($data['start_at'], $data['end_at'])) {
            return parent::handleRecordCreation($data);
        }

        return app(FlashSaleScheduleMutex::class)->runExclusive(function () use ($data): Model {
            $this->assertPendingItemsValid();

            $startAt = Carbon::parse($data['start_at']);
            $endAt = Carbon::parse($data['end_at']);
            $bookIds = array_column($this->pendingItems, 'book_id');

            app(FlashSaleCampaignValidator::class)->assertScheduledCampaignRules(
                $startAt,
                $endAt,
                bookIds: $bookIds,
                nested: true,
            );

            return DB::transaction(function () use ($data): Model {
                /** @var Promotion $promotion */
                $promotion = Promotion::query()->create($data);

                foreach ($this->pendingItems as $itemData) {
                    $promotion->items()->create($itemData);
                }

                return $promotion;
            });
        });
    }

    /**
     * @param  list<mixed>  $items
     * @return list<array{book_id: int, discount_value: int, stock_limit: ?int, max_quantity_per_user: ?int}>
     */
    private function normalizePendingItems(array $items): array
    {
        $normalized = [];

        foreach ($items as $row) {
            if (! is_array($row)) {
                continue;
            }

            $bookId = (int) ($row['book_id'] ?? 0);

            if ($bookId <= 0) {
                continue;
            }

            $discountValue = (int) ($row['discount_value'] ?? 0);

            if ($discountValue < 1 || $discountValue > 100) {
                throw ValidationException::withMessages([
                    'items' => ['Phần trăm giảm phải từ 1 đến 100.'],
                ]);
            }

            $stockLimit = $row['stock_limit'] ?? null;
            $maxPerUser = $row['max_quantity_per_user'] ?? null;

            $normalized[] = [
                'book_id' => $bookId,
                'discount_value' => $discountValue,
                'stock_limit' => filled($stockLimit) ? (int) $stockLimit : null,
                'max_quantity_per_user' => filled($maxPerUser) ? (int) $maxPerUser : null,
            ];
        }

        return $normalized;
    }

    private function assertPendingItemsValid(): void
    {
        if ($this->pendingItems === []) {
            throw ValidationException::withMessages([
                'items' => ['Phải có ít nhất một sản phẩm trong chiến dịch.'],
            ]);
        }

        $bookIds = array_column($this->pendingItems, 'book_id');

        if (count($bookIds) !== count(array_unique($bookIds))) {
            throw ValidationException::withMessages([
                'items' => ['Mỗi sách chỉ được thêm một lần trong chiến dịch.'],
            ]);
        }

        foreach ($this->pendingItems as $item) {
            if ($item['stock_limit'] !== null && $item['stock_limit'] < 1) {
                throw ValidationException::withMessages([
                    'items' => ['Giới hạn suất bán phải lớn hơn 0.'],
                ]);
            }

            if ($item['max_quantity_per_user'] !== null && $item['max_quantity_per_user'] < 1) {
                throw ValidationException::withMessages([
                    'items' => ['Tối đa mỗi khách phải lớn hơn 0.'],
                ]);
            }
        }
    }
}
