<?php

namespace App\Notifications\Inventory;

use App\Filament\Resources\InventoryResource;
use App\Services\Inventory\LowStockAlertItem;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class LowStockBooksNotification extends Notification
{
    use Queueable;

    /**
     * @param  Collection<int, LowStockAlertItem>  $items
     */
    public function __construct(
        public readonly Collection $items,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $bookCount = $this->items->pluck('bookId')->unique()->count();
        $previewLimit = (int) config('inventory.low_stock_notification_preview_limit', 5);

        $lines = $this->items
            ->take($previewLimit)
            ->map(fn (LowStockAlertItem $item): string => sprintf(
                '%s (%s): còn %d — %s',
                $item->bookName,
                $item->sku,
                $item->availableStock,
                $item->warehouseName,
            ))
            ->implode("\n");

        if ($this->items->count() > $previewLimit) {
            $lines .= "\n… và ".($this->items->count() - $previewLimit).' dòng tồn kho khác';
        }

        return FilamentNotification::make()
            ->title("Có {$bookCount} sách sắp hết hàng")
            ->body($lines)
            ->warning()
            ->actions([
                Action::make('view_inventory')
                    ->label('Xem tồn kho')
                    ->url(InventoryResource::lowStockListUrl())
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
