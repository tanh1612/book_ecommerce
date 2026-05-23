<?php

namespace App\Notifications\Inventory;

use App\Enums\Inventory\InventoryStockAlertType;
use App\Filament\Resources\InventoryResource;
use App\Models\Inventory;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class InventoryStockStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Inventory $inventory,
        public readonly InventoryStockAlertType $alertType,
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
        $book = $this->inventory->book;
        $warehouse = $this->inventory->warehouse;
        $available = $this->inventory->available_stock;

        $title = match ($this->alertType) {
            InventoryStockAlertType::LowStock => 'Sách sắp hết hàng',
            InventoryStockAlertType::OutOfStock => 'Sách đã hết hàng',
        };

        $body = sprintf(
            "%s (%s)\nTồn khả dụng: %d\nKho: %s\nVị trí: %s",
            $book?->name ?? '—',
            $book?->sku ?? '—',
            $available,
            $warehouse?->name ?? '—',
            $this->inventory->location_code !== '' ? $this->inventory->location_code : '—',
        );

        $filterStatus = match ($this->alertType) {
            InventoryStockAlertType::LowStock => 'low',
            InventoryStockAlertType::OutOfStock => 'out_of_stock',
        };

        $notification = FilamentNotification::make()
            ->title($title)
            ->body($body)
            ->actions([
                Action::make('view_inventory')
                    ->label('Xem tồn kho')
                    ->url(InventoryResource::stockStatusListUrl($filterStatus))
                    ->markAsRead(),
            ]);

        return match ($this->alertType) {
            InventoryStockAlertType::LowStock => $notification->warning()->getDatabaseMessage(),
            InventoryStockAlertType::OutOfStock => $notification->danger()->getDatabaseMessage(),
        };
    }
}
